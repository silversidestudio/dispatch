<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitation extends Notification
{
    public function __construct(public string $company, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation to join '.config('app.name'))
            ->line('You have been invited to join '.$this->company.'.')
            ->action('Open invitation', $this->url)
            ->line('This invitation expires in seven days and must be used with the invited email address.')
            ->line('If you were not expecting an invitation, you can ignore this email.');
    }
}
