<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailingList extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }
}
