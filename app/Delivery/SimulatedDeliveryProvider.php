<?php

namespace App\Delivery;

final class SimulatedDeliveryProvider implements DeliveryProvider
{
    public function submit(DeliveryMessage $message, int $attempt): SubmissionResult
    {
        $local = strtolower(strtok($message->to, '@'));
        $outcome = str_starts_with($local, 'permanent') ? 'rejected' : ((str_starts_with($local, 'temporary') && $attempt === 1) ? 'deferred' : 'accepted');

        return new SubmissionResult($outcome, 'sim_'.hash('sha256', $message->idempotencyKey));
    }
}
