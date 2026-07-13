<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Capacity;
use App\Enums\TimeSlotEnum;
use Carbon\Carbon;

class CapacitySeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $carbonDate = Carbon::now()->addDays($i);
            $date = $carbonDate->format('Y-m-d');
            $isWeekend = $carbonDate->isWeekend();
            
            Capacity::create([
                'date' => $date,
                'slot' => TimeSlotEnum::MORNING->value,
                'quota' => 5,
                'booked' => 0,
                'is_active' => $isWeekend, // Buka hanya Sabtu-Minggu
            ]);

            Capacity::create([
                'date' => $date,
                'slot' => TimeSlotEnum::AFTERNOON->value,
                'quota' => 5,
                'booked' => 0,
                'is_active' => true,
            ]);
        }
    }
}
