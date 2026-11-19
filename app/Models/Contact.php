<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

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
