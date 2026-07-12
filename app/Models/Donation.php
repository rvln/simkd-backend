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

    protected static function booted()
    {
        static::saving(function ($donation) {
            $type = $donation->type instanceof \BackedEnum ? $donation->type->value : $donation->type;

            if ($type === \App\Enums\DonationTypeEnum::DANA->value) {
                if (is_null($donation->amount) || $donation->amount <= 0) {
                    throw new \InvalidArgumentException('Jumlah donasi (amount) harus diisi untuk tipe donasi DANA.');
                }

                if ($donation->exists && $donation->itemDonations()->count() > 0) {
                    throw new \InvalidArgumentException('Donasi finansial (DANA) tidak boleh memiliki item donasi barang.');
                }
            } elseif ($type === \App\Enums\DonationTypeEnum::BARANG->value) {
                if (!is_null($donation->amount)) {
                    throw new \InvalidArgumentException('Jumlah donasi (amount) harus bernilai null untuk tipe donasi BARANG.');
                }

                if ($donation->exists) {
                    $status = $donation->status instanceof \BackedEnum ? $donation->status->value : $donation->status;
                    if (in_array($status, [\App\Enums\DonationStatusEnum::PENDING_DELIVERY->value, \App\Enums\DonationStatusEnum::SUCCESS->value])) {
                        if ($donation->itemDonations()->count() === 0) {
                            throw new \InvalidArgumentException('Donasi barang harus memiliki minimal satu item barang.');
                        }
                    }
                }
            }
        });
    }

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
