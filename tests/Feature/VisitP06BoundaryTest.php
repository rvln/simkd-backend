<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use App\Models\Capacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class VisitP06BoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // P-06 logic specifically checks for role PENGURUS_PANTI or KEPALA_PANTI
        $this->staff = User::factory()->create([
            'role' => 'PENGURUS_PANTI',
            'email_verified_at' => now(),
        ]);
    }

    public function test_pending_visits_excludes_expired_slots_today()
    {
        // Set fixed time to 11:00 AM today (Asia/Makassar)
        // At 11:00 AM, the MORNING slot (cutoff 10:00) is expired.
        // The AFTERNOON slot (cutoff 15:00) is still valid.
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 11, 0, 0, 'Asia/Makassar'));

        // 1. Create a Capacity & Visit for TODAY - MORNING (Expired)
        $capacityMorning = Capacity::create([
            'date' => '2024-01-01',
            'slot' => 'MORNING',
            'quota' => 10,
            'booked' => 0
        ]);
        Visit::create([
            'user_id' => $this->staff->id,
            'capacity_id' => $capacityMorning->id,
            'status' => 'PENDING',
            'visit_date' => '2024-01-01',
            'visitor_name' => 'Test',
            'brings_donation' => false
        ]);

        // 2. Create a Capacity & Visit for TODAY - AFTERNOON (Valid)
        $capacityAfternoon = Capacity::create([
            'date' => '2024-01-01',
            'slot' => 'AFTERNOON',
            'quota' => 10,
            'booked' => 0
        ]);
        Visit::create([
            'user_id' => $this->staff->id,
            'capacity_id' => $capacityAfternoon->id,
            'status' => 'PENDING',
            'visit_date' => '2024-01-01',
            'visitor_name' => 'Test',
            'brings_donation' => false
        ]);

        // 3. Create a Capacity & Visit for TOMORROW - MORNING (Valid)
        $capacityTomorrow = Capacity::create([
            'date' => '2024-01-02',
            'slot' => 'MORNING',
            'quota' => 10,
            'booked' => 0
        ]);
        Visit::create([
            'user_id' => $this->staff->id,
            'capacity_id' => $capacityTomorrow->id,
            'status' => 'PENDING',
            'visit_date' => '2024-01-02',
            'visitor_name' => 'Test',
            'brings_donation' => false
        ]);

        $response = $this->actingAs($this->staff)->getJson('/api/dashboard/overview');

        $response->assertStatus(200);

        // We expect only 2 pending visits (Today Afternoon and Tomorrow Morning)
        $response->assertJsonPath('metrics.pending_visits', 2);
    }
}
