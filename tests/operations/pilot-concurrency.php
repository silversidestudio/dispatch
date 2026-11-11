<?php

use App\Exceptions\PilotSubmissionBlocked;
use App\Services\SendGridPilotGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Standalone destructive-to-own-fixtures test. Never accepts a development/production database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'dispatch_test' || ! function_exists('pcntl_fork')) {
    fwrite(STDERR, "Requires APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=dispatch_test and pcntl.\n");
    exit(2);
}

$day = '2099-01-01';
Carbon::setTestNow($day.' 12:00:00 UTC');
config(['dispatch.pilot.enabled' => true, 'dispatch.pilot.recipients' => ['pilot@example.test'], 'dispatch.pilot.organisation_ids' => ['999'], 'dispatch.sendgrid.auth_enabled' => false]);
$dir = sys_get_temp_dir().'/dispatch-pilot-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
$clean = function () use ($day) {
    $ids = DB::table('sendgrid_pilot_budgets')->where('day', $day)->pluck('id');
    DB::table('sendgrid_pilot_reservations')->whereIn('budget_id', $ids)->delete();
    DB::table('sendgrid_pilot_budgets')->whereIn('id', $ids)->delete();
};
$failed = false;
try {
    foreach ([['cap', 3, 3], ['duplicate', 25, 1]] as [$scenario, $limit, $expected]) {
        $clean();
        config(['dispatch.pilot.daily_limit' => $limit]);
        DB::disconnect(); // Forks must never share a PDO connection.
        $pids = [];
        for ($i = 0; $i < 8; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Could not fork test worker.');
            }
            if ($pid === 0) {
                while (! file_exists($dir.'/go-'.$scenario)) {
                    usleep(1000);
                }
                try {
                    app(SendGridPilotGuard::class)->reserve('pilot@example.test', $scenario === 'duplicate' ? 'same-attempt' : 'attempt-'.$i, 999);
                    file_put_contents($dir.'/'.$scenario.'-'.$i, 'reserved');
                } catch (PilotSubmissionBlocked) {
                    file_put_contents($dir.'/'.$scenario.'-'.$i, 'blocked');
                } catch (Throwable $e) {
                    file_put_contents($dir.'/'.$scenario.'-'.$i, 'error:'.get_class($e).':'.$e->getCode().':'.($e instanceof QueryException ? ($e->errorInfo[1] ?? '') : ''));
                }
                exit(0);
            }
            $pids[] = $pid;
        }
        touch($dir.'/go-'.$scenario);
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        $results = [];
        for ($i = 0; $i < 8; $i++) {
            $results[] = file_get_contents($dir.'/'.$scenario.'-'.$i);
        }
        $reserved = count(array_filter($results, fn ($r) => $r === 'reserved'));
        $blocked = count(array_filter($results, fn ($r) => $r === 'blocked'));
        $used = (int) DB::table('sendgrid_pilot_budgets')->where('day', $day)->value('reserved');
        if ($reserved !== $expected || $blocked !== 8 - $expected || $used !== $expected) {
            throw new RuntimeException(json_encode(compact('scenario', 'results', 'used')));
        }
        echo "$scenario: $reserved reserved, $blocked blocked, $used durable budget slots (8 workers); PASS\n";
    }
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, $e->getMessage()."\n");
} finally {
    $clean();
    foreach (glob($dir.'/*') as $file) {
        unlink($file);
    }
    rmdir($dir);
    Carbon::setTestNow();
}

exit($failed ? 1 : 0);
