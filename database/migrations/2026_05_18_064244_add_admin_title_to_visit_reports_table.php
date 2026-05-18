<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds admin_title (nullable) to visit_reports.
     * Filled by admin during moderation; shown as card title on the landing page.
     */
    public function up(): void
    {
        Schema::table('visit_reports', function (Blueprint $table) {
            $table->string('admin_title', 120)->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_reports', function (Blueprint $table) {
            $table->dropColumn('admin_title');
        });
    }
};

