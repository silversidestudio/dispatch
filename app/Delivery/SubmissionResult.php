<?php

namespace App\Delivery;

final readonly class SubmissionResult
{
    public function __construct(public string $outcome, public string $messageId) {}
}
