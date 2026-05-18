<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\RoleEnum;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'google_id',
        'email_verified_at',
        'verification_token',
        'verification_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'verification_token',
        'verification_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
        ];
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function visitReports()
    {
        return $this->hasMany(VisitReport::class);
    }

    public function rejectedLogs()
    {
        return $this->hasMany(RejectedLog::class, 'logged_by');
    }

    public function distributions()
    {
        return $this->hasMany(Distribution::class);
    }
}
