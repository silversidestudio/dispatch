<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organisation extends Model
{
    protected $attributes = ['status' => 'active'];

    protected $hidden = ['sendgrid_api_key', 'sendgrid_webhook_key'];

    protected $fillable = ['name', 'website', 'postal_address'];

    protected function casts(): array
    {
        return ['sendgrid_api_key' => 'encrypted', 'sendgrid_webhook_key' => 'encrypted', 'email_branding' => 'array', 'open_tracking_enabled' => 'boolean', 'click_tracking_enabled' => 'boolean', 'tracking_reviewed_at' => 'datetime'];
    }

    public function sendGridCredentialFingerprint(): ?string
    {
        return $this->sendgrid_api_key ? hash_hmac('sha256', $this->sendgrid_api_key, config('app.key')) : null;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
