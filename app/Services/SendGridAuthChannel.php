<?php

namespace App\Services;

use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Http;

class SendGridAuthChannel
{
    public function send($user, $notification): void
    {
        if (! config('dispatch.sendgrid.auth_enabled') || ! config('dispatch.sendgrid.enabled') || ! config('dispatch.sendgrid.live_enabled') || ! config('dispatch.sendgrid.api_key') || ! filter_var(config('dispatch.sendgrid.auth_from'), FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Account email delivery is not authorised or configured.');
        }
        $mail = $notification->toMail($user);
        $markdown = app(Markdown::class);
        $data = $mail->data();
        try {
            $response = Http::withToken(config('dispatch.sendgrid.api_key'))->timeout(15)->post('https://api.sendgrid.com/v3/mail/send', ['personalizations' => [['to' => [['email' => $user->email]], 'custom_args' => ['dispatch_auth_notification' => 'account-message']]], 'from' => ['email' => config('dispatch.sendgrid.auth_from'), 'name' => config('app.name')], 'subject' => $mail->subject, 'content' => [['type' => 'text/plain', 'value' => (string) $markdown->renderText('notifications::email', $data)], ['type' => 'text/html', 'value' => (string) $markdown->render('notifications::email', $data)]], 'tracking_settings' => ['open_tracking' => ['enable' => false], 'click_tracking' => ['enable' => false, 'enable_text' => false], 'subscription_tracking' => ['enable' => false]]]);
            if ($response->status() !== 202) {
                throw new \RuntimeException('Account email was not acknowledged.');
            }
        } catch (\Throwable) {
            throw new \RuntimeException('Account email outcome could not be confirmed. No automatic retry was made.');
        }
    }
}
