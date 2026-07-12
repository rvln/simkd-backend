<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // PENGURUS PANTI (Admin)
        User::create([
            'name' => 'Admin Pengurus',
            'email' => 'admin@empanti.com',
            'password' => Hash::make(env('DEFAULT_ADMIN_PASSWORD', Str::random(16))),
            'role' => RoleEnum::PENGURUS_PANTI->value,
            'email_verified_at' => Carbon::now(),
        ]);
    }
}
