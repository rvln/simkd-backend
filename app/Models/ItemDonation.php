<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemDonation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'donation_id',
        'inventory_id',
        'itemName_snapshot',
        'qty',
        'photo_url',
    ];

    protected static function booted()
    {
        static::saving(function ($itemDonation) {
            $donation = $itemDonation->donation;
            if ($donation) {
                $donationType = $donation->type instanceof \BackedEnum ? $donation->type->value : $donation->type;
                if ($donationType === \App\Enums\DonationTypeEnum::DANA->value) {
                    throw new \InvalidArgumentException('Tidak dapat menambahkan item donasi ke donasi tipe DANA.');
                }
            }
        });
    }

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
