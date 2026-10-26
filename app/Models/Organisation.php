<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organisation extends Model
{
    protected $attributes = ['status' => 'active'];

    protected $fillable = ['name', 'website', 'postal_address'];

    protected function casts(): array
    {
        return ['email_branding' => 'array', 'open_tracking_enabled' => 'boolean', 'click_tracking_enabled' => 'boolean', 'tracking_reviewed_at' => 'datetime'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
