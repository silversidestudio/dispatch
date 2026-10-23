<?php

namespace App\Delivery;

interface DeliveryProvider
{
    public function submit(DeliveryMessage $message, int $attempt): SubmissionResult;
}
