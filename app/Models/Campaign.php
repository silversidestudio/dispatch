<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'live_authorised_at' => 'datetime', 'open_tracking' => 'boolean', 'click_tracking' => 'boolean', 'scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
