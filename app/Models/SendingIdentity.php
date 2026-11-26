<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendingIdentity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'provider_authorised' => 'boolean', 'verified_at' => 'datetime', 'dmarc_reviewed_at' => 'datetime'];
    }

    public function dmarcReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dmarc_reviewed_by_user_id');
    }
}
