<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class UnsubscribeService
{
    public function token(Contact $contact): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString(json_encode(['organisation_id' => $contact->organisation_id, 'contact_id' => $contact->id, 'email' => $contact->email], JSON_THROW_ON_ERROR))), '+/', '-_'), '=');
    }

    public function url(Contact $contact): string
    {
        return URL::signedRoute('unsubscribe.show', ['token' => $this->token($contact)], absolute: false);
    }

    public function contact(string $token): Contact
    {
        $d = json_decode(Crypt::decryptString(base64_decode(strtr($token, '-_', '+/'), true)), true, 512, JSON_THROW_ON_ERROR);

        return Contact::where('organisation_id', $d['organisation_id'])->where('email', $d['email'])->findOrFail($d['contact_id']);
    }

    public function apply(string $token): void
    {
        $c = $this->contact($token);
        DB::transaction(function () use ($c) {
            app(EligibilityService::class)->suppress($c, 'unsubscribed', 'Recipient unsubscribe confirmation.');
        });
    }
}
