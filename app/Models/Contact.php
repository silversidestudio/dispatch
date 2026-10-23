<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attributes' => 'array'];
    }

    public function lists()
    {
        return $this->belongsToMany(MailingList::class)->withTimestamps();
    }
}
