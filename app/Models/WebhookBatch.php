<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['events' => 'encrypted:array'];
    }
}
