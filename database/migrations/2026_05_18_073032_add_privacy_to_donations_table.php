<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add donor_name_privacy to donations table.
     * Controls how the donor's name is displayed publicly in "Jejak Kebaikan".
     *
     * Values:
     *  'show' — PII-masked display name (default)
     *  'hide' — displayed as "Hamba Allah"
     *  'anon' — displayed as the chosen alias (stored in donorName)
     */
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('donor_name_privacy', 10)->default('show')->after('donorPhone');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('donor_name_privacy');
        });
    }
};
