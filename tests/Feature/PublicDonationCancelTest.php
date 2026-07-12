<?php

namespace Tests\Feature;

use App\Enums\DonationStatusEnum;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class PublicDonationCancelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that donation cancellation fails if no tracking code is provided.
     */
    public function test_cancel_fails_without_tracking_code()
    {
        $donation = Donation::create([
            'id' => Str::uuid()->toString(),
            'status' => DonationStatusEnum::PENDING->value,
            'tracking_code' => 'TXN-DON-1234',
            'type' => 'DANA',
            'payment_channel' => 'MANUAL',
            'amount' => 100000,
            'donorName' => 'John Doe',
            'donorPhone' => '081234567890',
            'donorEmail' => 'john@example.com',
        ]);

        $response = $this->patchJson("/api/public/donations/{$donation->id}/cancel", []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['tracking_code']);
    }

    /**
     * Test that donation cancellation fails with incorrect tracking code.
     */
    public function test_cancel_fails_with_invalid_tracking_code()
    {
        $donation = Donation::create([
            'id' => Str::uuid()->toString(),
            'status' => DonationStatusEnum::PENDING->value,
            'tracking_code' => 'TXN-DON-1234',
            'type' => 'DANA',
            'payment_channel' => 'MANUAL',
            'amount' => 100000,
            'donorName' => 'John Doe',
            'donorPhone' => '081234567890',
            'donorEmail' => 'john@example.com',
        ]);

        $response = $this->patchJson("/api/public/donations/{$donation->id}/cancel", [
            'tracking_code' => 'WRONG-CODE',
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Tracking code tidak valid atau Anda tidak memiliki akses untuk membatalkan donasi ini.']);
    }

    /**
     * Test that donation cancellation succeeds with correct tracking code.
     */
    public function test_cancel_succeeds_with_correct_tracking_code()
    {
        $donation = Donation::create([
            'id' => Str::uuid()->toString(),
            'status' => DonationStatusEnum::PENDING->value,
            'tracking_code' => 'TXN-DON-1234',
            'type' => 'DANA',
            'payment_channel' => 'MANUAL',
            'amount' => 100000,
            'donorName' => 'John Doe',
            'donorPhone' => '081234567890',
            'donorEmail' => 'john@example.com',
        ]);

        $response = $this->patchJson("/api/public/donations/{$donation->id}/cancel", [
            'tracking_code' => 'TXN-DON-1234',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => 'success', 'message' => 'Donasi berhasil dibatalkan.']);

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => DonationStatusEnum::EXPIRED->value,
        ]);
    }
}
