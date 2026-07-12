<?php

namespace Tests\Feature;

use App\Enums\VisitStatusEnum;
use App\Models\Capacity;
use App\Models\User;
use App\Models\Visit;
use App\Services\CapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CapacityRescheduleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that rescheduling transfers the booked count correctly.
     */
    public function test_reschedule_transfers_booked_count_and_prevents_overbooking()
    {
        $user = User::factory()->create();

        $oldCapacity = Capacity::create([
            'id' => Str::uuid()->toString(),
            'date' => now()->addDays(1)->format('Y-m-d'),
            'slot' => 'MORNING',
            'quota' => 5,
            'booked' => 1,
            'is_active' => true,
        ]);

        $newCapacity = Capacity::create([
            'id' => Str::uuid()->toString(),
            'date' => now()->addDays(2)->format('Y-m-d'),
            'slot' => 'AFTERNOON',
            'quota' => 2,
            'booked' => 1,
            'is_active' => true,
        ]);

        $visit = Visit::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'capacity_id' => $oldCapacity->id,
            'status' => VisitStatusEnum::NEEDS_RESCHEDULE->value,
            'visitor_type' => 'INDIVIDU',
            'visitor_count' => 1,
        ]);

        $service = app(CapacityService::class);

        // Perform reschedule
        $result = $service->processReschedule($visit->id, $user->id, $newCapacity->id);

        $this->assertEquals(VisitStatusEnum::PENDING->value, $result['status']);
        $this->assertTrue($result['is_rescheduled']);

        // Assert booked counts transferred
        $this->assertEquals(0, $oldCapacity->fresh()->booked);
        $this->assertEquals(2, $newCapacity->fresh()->booked); // Was 1, now 2 (full)

        // Now try to reschedule another visit into the new capacity (which is now full)
        $visit2 = Visit::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'capacity_id' => $oldCapacity->id,
            'status' => VisitStatusEnum::NEEDS_RESCHEDULE->value,
            'visitor_type' => 'INDIVIDU',
            'visitor_count' => 1,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Slot yang dipilih sudah penuh.');

        $service->processReschedule($visit2->id, $user->id, $newCapacity->id);
    }
}
