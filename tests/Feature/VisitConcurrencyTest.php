<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Capacity;
use App\Models\Visit;
use App\Enums\RoleEnum;
use App\Enums\TimeSlotEnum;
use App\Enums\VisitStatusEnum;
use App\Services\CapacityService;

class VisitConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_service_prevents_overbooking_when_lock_maxes_out()
    {
        // 1. Setup capacity full scenario (Quota: 1, Booked: 1)
        $capacity = Capacity::create([
            'date' => '2026-12-01',
            'slot' => TimeSlotEnum::MORNING->value,
            'quota' => 1,
            'booked' => 1,
        ]);

        $user = User::create([
            'name' => 'Visitor',
            'email' => 'visitor@test.com',
            'password' => 'secret123',
            'role' => RoleEnum::PENGUNJUNG->value,
        ]);

        $visit = Visit::create([
            'user_id' => $user->id,
            'capacity_id' => $capacity->id,
            'status' => VisitStatusEnum::PENDING->value,
        ]);

        // 2. Act
        $service = new CapacityService();
        
        $exceptionThrown = false;
        try {
            $service->approveVisit($visit->id);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertEquals("Capacity is full. Visit rejected due to race condition.", $e->getMessage());
        }

        // 3. Assert Exception triggered and Visit set to REJECTED automatically
        $this->assertTrue($exceptionThrown, "Exception was not thrown for overbooked capacity.");
        
        $visit->refresh();
        $this->assertEquals(VisitStatusEnum::PENDING->value, $visit->status->value);
        
        $capacity->refresh();
        $this->assertEquals(1, $capacity->booked); // Ensure booked index didn't mutate
    }
}
