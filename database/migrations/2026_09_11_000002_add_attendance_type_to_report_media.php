<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance media (meeting 2026-09 requirement G) reuses the private
 * report_media architecture. A new 'attendance' type is added so evidence
 * photos attached to a report's attendance section live in the same table,
 * disk and authorized serving route as existing report media.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->enum('type', ['photo', 'video', 'attendance'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->enum('type', ['photo', 'video'])->change();
        });
    }
};
