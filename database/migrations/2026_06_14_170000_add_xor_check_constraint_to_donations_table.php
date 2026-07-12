<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE donations ADD CONSTRAINT chk_donation_type_amount CHECK ((type = 'DANA' AND amount IS NOT NULL) OR (type = 'BARANG' AND amount IS NULL))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE donations DROP CHECK chk_donation_type_amount");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE donations DROP CONSTRAINT chk_donation_type_amount");
        }
    }
};
