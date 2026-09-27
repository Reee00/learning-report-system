<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pola jadwal per HARI + SEKOLAH (refactor 2026-09-25).
 *
 * Sebelumnya satu periode global dipakai untuk semua sekolah: operator mengisi
 * satu `period_start`/`period_end`/`meetings_target` untuk seluruh workbook,
 * padahal workbook operasional DIGISchool memberi tanggal mulai PER SEKOLAH di
 * kolom KET ("Mulai tanggal 3 Agustus 2026", "Mulai tanggal 10 Agustus 2026").
 *
 * Perubahan konsep:
 * - `period_start`  -> `start_date`     : anchor pertemuan ke-1 pola ini.
 * - `meetings_target` -> `meeting_count`: jumlah pertemuan pola ini.
 * - `period_end`    -> DIBUANG          : turunan dari start_date + meeting_count.
 * - `period_name`   -> `pattern_name`   : label pola, boleh kosong.
 *
 * Pola diidentifikasi oleh (day_of_week, school_id, start_date) — karenanya
 * ditambahkan index gabungan untuk lookup dan pengelompokan.
 *
 * Data lama aman: `start_date` di-backfill ke kemunculan pertama hari pola
 * pada/atau setelah tanggal periode lama, yang persis sama dengan tanggal
 * pertemuan ke-1 hasil generate sebelumnya.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->renameColumn('period_name', 'pattern_name');
            $table->renameColumn('period_start', 'start_date');
            $table->renameColumn('meetings_target', 'meeting_count');
        });

        // Index lama (period_start, period_end) harus dilepas SEBELUM kolomnya
        // dibuang: SQLite menolak DROP COLUMN selama kolomnya masih terindeks.
        // Index ini juga sudah tidak relevan karena rentang periode global
        // digantikan oleh start_date + meeting_count per pola.
        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->dropIndex('teaching_schedule_templates_period_start_period_end_index');
        });

        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->dropColumn('period_end');
        });

        // Backfill: anchor tiap baris ke hari polanya sendiri. Baris lama
        // mewarisi satu tanggal periode global yang belum tentu jatuh pada
        // hari polanya; menggeser maju ke hari yang cocok menghasilkan
        // tanggal pertemuan ke-1 yang identik dengan hasil generate lama.
        DB::table('teaching_schedule_templates')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('teaching_schedule_templates')->where('id', $row->id)->update([
                    'start_date'    => $this->anchor($row->start_date, (int) $row->day_of_week),
                    'meeting_count' => ((int) $row->meeting_count) > 0 ? (int) $row->meeting_count : 20,
                ]);
            }
        });

        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->index(['day_of_week', 'school_id', 'start_date'], 'tst_pattern_idx');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->dropIndex('tst_pattern_idx');
        });

        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->date('period_end')->nullable()->after('start_date');
        });

        DB::table('teaching_schedule_templates')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $start = Carbon::parse($row->start_date);
                DB::table('teaching_schedule_templates')->where('id', $row->id)->update([
                    'pattern_name'   => $row->pattern_name ?: 'Pola lama',
                    'period_end'     => $start->copy()->addWeeks(max(1, (int) $row->meeting_count) - 1)->toDateString(),
                    'meetings_target' => (int) $row->meeting_count,
                ]);
            }
        });

        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->renameColumn('pattern_name', 'period_name');
            $table->renameColumn('start_date', 'period_start');
            $table->renameColumn('meeting_count', 'meetings_target');
        });

        Schema::table('teaching_schedule_templates', function (Blueprint $table) {
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Kemunculan pertama `$isoDay` pada/atau setelah `$date` (ISO 1=Senin).
     */
    private function anchor(?string $date, int $isoDay): string
    {
        $cursor = Carbon::parse($date ?? today()->toDateString())->startOfDay();
        $isoDay = $isoDay >= 1 && $isoDay <= 7 ? $isoDay : 1;

        while ((int) $cursor->isoWeekday() !== $isoDay) {
            $cursor->addDay();
        }

        return $cursor->toDateString();
    }
};
