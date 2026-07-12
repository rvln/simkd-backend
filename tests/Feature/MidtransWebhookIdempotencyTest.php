<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;

class MidtransWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_enforces_idempotency_on_duplicate_settlement_payloads()
    {
        // 1. Seed Initial State
        $orderId = 'DON-TEST-123';
        $donation = Donation::create([
            'order_id' => $orderId,
            'donorName' => 'Test Donor',
            'donorEmail' => 'test@example.com',
            'donorPhone' => '08123456789',
            'type' => DonationTypeEnum::DANA->value,
            'amount' => 50000,
            'status' => DonationStatusEnum::PENDING->value,
        ]);

        $payload = [
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '50000',
            'signature_key' => hash('sha512', $orderId . '200' . '50000' . config('midtrans.server_key')),
        ];

        // 2. First execution
        $response1 = $this->postJson('/api/webhooks/midtrans', $payload);
        $response1->assertStatus(200);

        $donation->refresh();
        $this->assertEquals(DonationStatusEnum::SUCCESS->value, $donation->status->value);
        $this->assertNotNull($donation->tracking_code);
        $trackingCode = $donation->tracking_code;

        // 3. Second execution (Duplicate Webhook)
        $response2 = $this->postJson('/api/webhooks/midtrans', $payload);
        
        // Assert it returns 200 early without parsing logic
        $response2->assertStatus(200);

        $donation->refresh();
        $this->assertEquals(DonationStatusEnum::SUCCESS->value, $donation->status->value);
        $this->assertEquals($trackingCode, $donation->tracking_code); // Implicit validation
    }
}
