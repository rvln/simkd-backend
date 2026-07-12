<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\InventoryEnum;
use App\Enums\DonationStatusEnum;
use App\Models\ItemDonation;
use App\Models\Distribution;

class Inventory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'itemName',
        'category',
        'priority',
        'stock',
        'target_qty',
        'unit',
        'description',
    ];

    protected $casts = [
        'category' => InventoryEnum::class,
        'priority' => \App\Enums\PriorityEnum::class,
    ];

    // $appends dihapus untuk mencegah N+1 query otomatis.
    // Atribut virtual_stock, dll akan dikalkulasi secara manual via withSum() 
    // atau diakses langsung hanya saat benar-benar dibutuhkan.

    public function getStatusKebutuhanAttribute()
    {
        return ($this->stock + $this->virtual_stock) >= $this->target_qty ? 'TERPENUHI' : 'SEDANG BERLANGSUNG';
    }

    public function getIsDisabledAttribute()
    {
        return ($this->stock + $this->virtual_stock) >= $this->target_qty;
    }

    public function getNextAvailableDateAttribute()
    {
        \Carbon\Carbon::setLocale('id');
        return \Carbon\Carbon::now('Asia/Makassar')->addMonth()->startOfMonth()->translatedFormat('j F Y');
    }

    public function itemDonations()
    {
        return $this->hasMany(ItemDonation::class);
    }

    public function distributions()
    {
        return $this->hasMany(Distribution::class);
    }
}
