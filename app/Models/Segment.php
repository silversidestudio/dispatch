<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }
}
