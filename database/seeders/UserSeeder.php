<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // PENGURUS PANTI (Admin)
        User::create([
            'name' => 'Admin Pengurus',
            'email' => 'admin@empanti.com',
            'password' => Hash::make('password123'),
            'role' => RoleEnum::PENGURUS_PANTI->value,
            'email_verified_at' => Carbon::now(),
        ]);

        // KEPALA PANTI (Headmaster)
        User::create([
            'name' => 'Dr. J. Lucas (Kepala)',
            'email' => 'kepala@empanti.com',
            'password' => Hash::make('password123'),
            'role' => RoleEnum::KEPALA_PANTI->value,
            'email_verified_at' => Carbon::now(),
        ]);

        // PENGUNJUNG (Verified)
        User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
            'role' => RoleEnum::PENGUNJUNG->value,
            'email_verified_at' => Carbon::now(),
        ]);

        // PENGUNJUNG (Unverified)
        User::create([
            'name' => 'Siti Aminah',
            'email' => 'siti@example.com',
            'password' => Hash::make('password123'),
            'role' => RoleEnum::PENGUNJUNG->value,
            'email_verified_at' => null,
        ]);
    }
}
