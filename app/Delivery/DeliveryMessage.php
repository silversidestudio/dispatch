<?php

namespace App\Delivery;

final readonly class DeliveryMessage
{
    public function __construct(public string $idempotencyKey, public string $to, public string $from, public string $subject, public string $html, public string $text, public ?string $replyTo = null, public bool $sandbox = true, public bool $liveAuthorised = false, public bool $openTracking = false, public bool $clickTracking = false, public array $headers = [], public string $fromName = '') {}
}
