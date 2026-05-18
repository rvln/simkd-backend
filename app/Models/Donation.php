<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use App\Models\ItemDonation;
use App\Models\User;
use App\Models\Visit;

class Donation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'visit_id',
        'order_id',
        'donorName',
        'donorEmail',
        'donorPhone',
        'donor_name_privacy',
        'type',
        'amount',
        'snap_token',
        'payment_type',
        'payment_channel',
        'payment_proof',
        'status',
        'tracking_code',
        'expires_at',
    ];

    protected $casts = [
        'status' => DonationStatusEnum::class,
        'type' => DonationTypeEnum::class,
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function itemDonations()
    {
        return $this->hasMany(ItemDonation::class);
    }

    public function rejectedLogs()
    {
        return $this->hasMany(RejectedLog::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
