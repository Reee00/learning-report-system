<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA M-005: unique constraint on report_attendances (report_id, student_id).
 *
 * syncAttendance() uses delete+insert which is safe sequentially, but
 * concurrent requests or direct DB manipulation could create duplicates.
 * Before adding the index, duplicates (if any) are collapsed to the newest
 * row per (report_id, student_id) so the migration never fails on existing
 * data and no attendance record is silently invented.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Keep the newest row for each duplicated pair.
            DB::statement(
                'DELETE ra FROM report_attendances ra
                 INNER JOIN report_attendances newer
                     ON newer.report_id = ra.report_id
                    AND newer.student_id = ra.student_id
                    AND newer.id > ra.id'
            );
        } else {
            // SQLite has no DELETE ... JOIN.
            $duplicateIds = DB::select(
                'SELECT ra.id FROM report_attendances ra
                 WHERE EXISTS (
                     SELECT 1 FROM report_attendances newer
                     WHERE newer.report_id = ra.report_id
                       AND newer.student_id = ra.student_id
                       AND newer.id > ra.id
                 )'
            );
            if ($duplicateIds !== []) {
                DB::table('report_attendances')
                    ->whereIn('id', array_column($duplicateIds, 'id'))
                    ->delete();
            }
        }

        Schema::table('report_attendances', function (Blueprint $table) {
            $table->unique(['report_id', 'student_id'], 'report_attendances_report_student_unique');
        });
    }

    public function down(): void
    {
        // MySQL butuh indeks pengganti untuk FK report_id sebelum unique
        // index boleh di-drop (error 1553), jadi pasang indeks biasa dulu
        // (idempotent — percobaan rollback sebelumnya bisa sudah membuatnya).
        if (DB::getDriverName() === 'mysql'
            && !Schema::hasIndex('report_attendances', 'report_attendances_report_id_index')) {
            Schema::table('report_attendances', function (Blueprint $table) {
                $table->index('report_id', 'report_attendances_report_id_index');
            });
        }

        if (Schema::hasIndex('report_attendances', 'report_attendances_report_student_unique')) {
            Schema::table('report_attendances', function (Blueprint $table) {
                $table->dropUnique('report_attendances_report_student_unique');
            });
        }

        // Indeks pengganti report_id sengaja dipertahankan: MySQL memakai
        // indeks itu untuk FK constraint dan menolak drop-nya (error 1553).
    }
};
