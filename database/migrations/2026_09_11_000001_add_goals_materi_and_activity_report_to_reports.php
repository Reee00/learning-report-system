<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting 2026-09 requirement: "Ringkasan Kegiatan" is replaced by two separate
 * fields — Goals Materi and Activity Report.
 *
 * Incremental and non-destructive:
 * - new columns are added as nullable;
 * - existing activity_summary values are backfilled into activity_report so
 *   historical reports keep rendering;
 * - the legacy activity_summary column itself is kept (dormant) so the change
 *   can be rolled back without data loss.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('goals_materi')->nullable()->after('lesson_material');
            $table->text('activity_report')->nullable()->after('goals_materi');
        });

        // Backfill: the old summary becomes the activity report content.
        DB::table('reports')
            ->whereNull('activity_report')
            ->whereNotNull('activity_summary')
            ->update(['activity_report' => DB::raw('`activity_summary`')]);

        // Kolom lama menjadi dormant: dibuat nullable agar insert baru tidak
        // perlu mengisinya lagi (aplikasi sudah tidak menulis ke kolom ini).
        Schema::table('reports', function (Blueprint $table) {
            $table->text('activity_summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sebelum mengembalikan NOT NULL, pastikan tidak ada nilai NULL yang
        // tersisa di activity_summary (rollback aman untuk data lama).
        DB::table('reports')
            ->whereNull('activity_summary')
            ->update(['activity_summary' => DB::raw('`activity_report`')]);

        Schema::table('reports', function (Blueprint $table) {
            $table->text('activity_summary')->nullable(false)->change();
            $table->dropColumn(['goals_materi', 'activity_report']);
        });
    }
};
