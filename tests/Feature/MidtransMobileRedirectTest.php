<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Donation;
use App\Enums\DonationTypeEnum;
use App\Enums\DonationStatusEnum;
use Mockery;
use Midtrans\Snap;

class MidtransMobileRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_snap_payload_contains_mobile_redirect_callbacks()
    {
        // Mock the Midtrans\Snap class to intercept getSnapToken
        $snapMock = Mockery::mock('alias:Midtrans\Snap');
        
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $capturedParams = [];

        $snapMock->shouldReceive('getSnapToken')
            ->once()
            ->withArgs(function ($params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            })
            ->andReturn('dummy-snap-token-123');

        // Create a basic financial donation payload
        $payload = [
            'type' => DonationTypeEnum::DANA->value,
            'donorName' => 'Test Mobile User',
            'donorEmail' => 'test@mobile.com',
            'donorPhone' => '08123456789',
            'amount' => 50000,
            'items' => [], // Empty because it's DANA
            'payment_channel' => 'MIDTRANS',
        ];

        // Action
        $response = $this->postJson('/api/donasi/finansial', $payload);

        // Assert API success
        $response->assertStatus(201);
        
        // Assert the returned snap_token matches our mock
        $this->assertEquals('dummy-snap-token-123', $response->json('data.snap_token'));

        $donationId = $response->json('data.donation.id');

        // Assert the callbacks are properly set in the captured params
        $this->assertNotEmpty($capturedParams);
        $this->assertArrayHasKey('callbacks', $capturedParams);
        
        $callbacks = $capturedParams['callbacks'];
        $expectedUrl = "{$frontendUrl}/donasi/invoice/{$donationId}";

        $this->assertEquals($expectedUrl, $callbacks['finish']);
        $this->assertEquals($expectedUrl, $callbacks['unfinish']);
        $this->assertEquals($expectedUrl, $callbacks['error']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
