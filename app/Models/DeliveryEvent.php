<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime', 'simulated' => 'boolean'];
    }
}
