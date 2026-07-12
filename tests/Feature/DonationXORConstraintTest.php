<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Donation;
use App\Models\ItemDonation;
use App\Models\Inventory;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;

class DonationXORConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_dana_donation_succeeds_with_amount_and_no_items()
    {
        $donation = Donation::create([
            'donorName' => 'Alice',
            'donorEmail' => 'alice@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::DANA->value,
            'amount' => 100000,
            'status' => DonationStatusEnum::PENDING->value,
        ]);

        $this->assertNotNull($donation->id);
        $this->assertEquals(100000, $donation->amount);
    }

    public function test_dana_donation_fails_without_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jumlah donasi (amount) harus diisi untuk tipe donasi DANA.');

        Donation::create([
            'donorName' => 'Bob',
            'donorEmail' => 'bob@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::DANA->value,
            'amount' => null,
            'status' => DonationStatusEnum::PENDING->value,
        ]);
    }

    public function test_cannot_add_item_donation_to_dana_donation()
    {
        $donation = Donation::create([
            'donorName' => 'Alice',
            'donorEmail' => 'alice@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::DANA->value,
            'amount' => 100000,
            'status' => DonationStatusEnum::PENDING->value,
        ]);

        $inventory = Inventory::create([
            'itemName' => 'Buku',
            'category' => 'PENDIDIKAN',
            'stock' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tidak dapat menambahkan item donasi ke donasi tipe DANA.');

        ItemDonation::create([
            'donation_id' => $donation->id,
            'inventory_id' => $inventory->id,
            'itemName_snapshot' => 'Buku',
            'qty' => 5,
        ]);
    }

    public function test_barang_donation_fails_with_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jumlah donasi (amount) harus bernilai null untuk tipe donasi BARANG.');

        Donation::create([
            'donorName' => 'Charlie',
            'donorEmail' => 'charlie@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::BARANG->value,
            'amount' => 50000,
            'status' => DonationStatusEnum::PENDING->value,
        ]);
    }

    public function test_barang_donation_creation_succeeds_without_amount()
    {
        $donation = Donation::create([
            'donorName' => 'Charlie',
            'donorEmail' => 'charlie@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::BARANG->value,
            'amount' => null,
            'status' => DonationStatusEnum::PENDING->value,
        ]);

        $this->assertNotNull($donation->id);
        $this->assertNull($donation->amount);
    }

    public function test_barang_donation_fails_validation_if_set_to_pending_delivery_without_items()
    {
        $donation = Donation::create([
            'donorName' => 'Charlie',
            'donorEmail' => 'charlie@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::BARANG->value,
            'amount' => null,
            'status' => DonationStatusEnum::PENDING->value,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Donasi barang harus memiliki minimal satu item barang.');

        $donation->update([
            'status' => DonationStatusEnum::PENDING_DELIVERY->value,
        ]);
    }

    public function test_barang_donation_succeeds_with_items_when_set_to_pending_delivery()
    {
        $donation = Donation::create([
            'donorName' => 'Charlie',
            'donorEmail' => 'charlie@test.com',
            'donorPhone' => '0812345678',
            'type' => DonationTypeEnum::BARANG->value,
            'amount' => null,
            'status' => DonationStatusEnum::PENDING->value,
        ]);

        $inventory = Inventory::create([
            'itemName' => 'Pakaian',
            'category' => 'PAKAIAN',
            'stock' => 0,
        ]);

        ItemDonation::create([
            'donation_id' => $donation->id,
            'inventory_id' => $inventory->id,
            'itemName_snapshot' => 'Pakaian',
            'qty' => 10,
        ]);

        $donation->update([
            'status' => DonationStatusEnum::PENDING_DELIVERY->value,
        ]);

        $this->assertEquals(DonationStatusEnum::PENDING_DELIVERY->value, $donation->status->value);
        $this->assertEquals(1, $donation->itemDonations()->count());
    }
}
