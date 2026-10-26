<?php

namespace App\Models;

use App\Services\SendGridAuthChannel;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public function sendEmailVerificationNotification()
    {
        $notification = new VerifyEmail;
        if (config('dispatch.sendgrid.auth_enabled')) {
            app(SendGridAuthChannel::class)->send($this, $notification);
        } else {
            $this->notify($notification);
        }
    }

    public function sendPasswordResetNotification($token)
    {
        $notification = new ResetPassword($token);
        if (config('dispatch.sendgrid.auth_enabled')) {
            app(SendGridAuthChannel::class)->send($this, $notification);
        } else {
            $this->notify($notification);
        }
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class)->withPivot('role')->withTimestamps();
    }

    protected $attributes = ['status' => 'active'];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }
}
