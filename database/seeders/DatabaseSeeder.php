<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $user = User::firstOrCreate(['email' => 'viewer@example.test'], ['name' => 'Demonstration Viewer', 'password' => Str::random(48), 'email_verified_at' => now()]);
        $org = Organisation::firstOrCreate(['name' => 'Acorn Studio · Demo']);
        $org->members()->syncWithoutDetaching([$user->id => ['role' => 'viewer']]);
    }
}
