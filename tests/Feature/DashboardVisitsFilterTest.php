<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Visit;
use App\Models\Capacity;
use App\Enums\VisitStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DashboardVisitsFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expired_visits_are_not_counted_in_pending_visits()
    {
        // Mock current time to a specific time for the entire test
        Carbon::setTestNow(Carbon::create(2026, 7, 12, 16, 0, 0, 'Asia/Makassar'));

        // Create Staff User
        /** @var \App\Models\User $staff */
        $staff = User::factory()->create([
            'role' => \App\Enums\RoleEnum::PENGURUS_PANTI->value,
        ]);

        $visitor = User::factory()->create();

        // 1. Visit that is NOT expired (Tomorrow)
        $capacityFuture = Capacity::create([
            'id' => Str::uuid()->toString(),
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'slot' => 'MORNING',
            'quota' => 5,
            'booked' => 1,
            'is_active' => true,
        ]);

        Visit::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $visitor->id,
            'capacity_id' => $capacityFuture->id,
            'status' => VisitStatusEnum::PENDING->value,
            'visitor_type' => 'INDIVIDU',
            'visitor_count' => 1,
        ]);

        // 2. Visit that IS expired (Yesterday)
        $capacityPast = Capacity::create([
            'id' => Str::uuid()->toString(),
            'date' => Carbon::yesterday()->format('Y-m-d'),
            'slot' => 'MORNING',
            'quota' => 5,
            'booked' => 1,
            'is_active' => true,
        ]);

        Visit::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $visitor->id,
            'capacity_id' => $capacityPast->id,
            'status' => VisitStatusEnum::PENDING->value,
            'visitor_type' => 'INDIVIDU',
            'visitor_count' => 1,
        ]);

        // 3. Visit that IS expired (Today, Morning slot, but now is 16:00)
        $capacityTodayExpired = Capacity::create([
            'id' => Str::uuid()->toString(),
            'date' => Carbon::today()->format('Y-m-d'),
            'slot' => 'MORNING', // boundary is 10:00
            'quota' => 5,
            'booked' => 1,
            'is_active' => true,
        ]);

        Visit::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $visitor->id,
            'capacity_id' => $capacityTodayExpired->id,
            'status' => VisitStatusEnum::PENDING->value,
            'visitor_type' => 'INDIVIDU',
            'visitor_count' => 1,
        ]);

        // 4. Visit that is NOT expired (Today, Night slot, now is 16:00, boundary 20:00)
        $capacityTodayValid = Capacity::create([
            'id' => Str::uuid()->toString(),
            'date' => Carbon::today()->format('Y-m-d'),
            'slot' => 'NIGHT',
            'quota' => 5,
            'booked' => 1,
            'is_active' => true,
        ]);

        Visit::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $visitor->id,
            'capacity_id' => $capacityTodayValid->id,
            'status' => VisitStatusEnum::PENDING->value,
            'visitor_type' => 'INDIVIDU',
            'visitor_count' => 1,
        ]);

        // Action
        $response = $this->actingAs($staff)->getJson('/api/dashboard/overview');

        // Assert
        $response->assertStatus(200);
        $metrics = $response->json('metrics');

        // Only 2 visits should be counted: The future one (1) and the today valid one (4)
        $this->assertEquals(2, $metrics['pending_visits']);
    }
}
