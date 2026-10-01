<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SATU SESI = SATU LAPORAN (aturan bisnis final 2026-09-28).
 *
 * `reports` sebelumnya hanya menyimpan (coach_id, class_id, report_date), jadi
 * keunikan laporan tidak bisa dinyatakan pada SESINYA — dua coach pada sesi
 * yang sama sama-sama sah membuat baris laporan. Kolom `teaching_schedule_id`
 * memberi rujukan langsung ke sesi, dan indeks uniknya menutup celah balapan
 * (dua request bersamaan) yang tidak bisa dicegah validasi aplikasi saja.
 *
 * Migrasi ini inkremental dan aman:
 * - kolom NULLABLE: laporan lama tetap sah walau belum bisa ditautkan;
 * - backfill hanya menautkan laporan yang kandidat sesinya TUNGGAL dan sesi
 *   itu belum dipakai laporan lain — tidak ada baris yang dihapus/digabung;
 * - indeks unik pada kolom nullable: MySQL dan SQLite sama-sama mengizinkan
 *   banyak NULL, sehingga laporan tanpa sesi tidak saling bentrok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('teaching_schedule_id')
                ->nullable()
                ->after('class_id')
                ->constrained('teaching_schedules')
                ->nullOnDelete();
        });

        $this->backfill();

        Schema::table('reports', function (Blueprint $table) {
            $table->unique('teaching_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique(['teaching_schedule_id']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teaching_schedule_id');
        });
    }

    /**
     * Tautkan laporan lama ke sesinya bila hubungannya tidak ambigu.
     *
     * Kandidat = sesi pada kelas & tanggal yang sama, tempat coach pelapor
     * tercatat sebagai coach utama atau coach pendamping. Kalau kandidatnya
     * lebih dari satu (mis. dua sesi berbeda jam pada hari yang sama), laporan
     * dibiarkan tanpa tautan — lebih baik tanpa rujukan daripada salah rujuk.
     */
    private function backfill(): void
    {
        $claimed = DB::table('reports')
            ->whereNotNull('teaching_schedule_id')
            ->pluck('teaching_schedule_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $claimed = array_flip($claimed);

        DB::table('reports')
            ->whereNull('teaching_schedule_id')
            ->orderBy('id')
            ->chunkById(200, function ($reports) use (&$claimed): void {
                foreach ($reports as $report) {
                    $date = substr((string) $report->report_date, 0, 10);

                    $candidates = DB::table('teaching_schedules')
                        ->where('class_id', $report->class_id)
                        ->whereDate('session_date', $date)
                        ->where(function ($query) use ($report): void {
                            $query->where('coach_id', $report->coach_id)
                                ->orWhereExists(function ($sub) use ($report): void {
                                    $sub->selectRaw('1')
                                        ->from('teaching_schedule_coach')
                                        ->whereColumn('teaching_schedule_coach.schedule_id', 'teaching_schedules.id')
                                        ->where('teaching_schedule_coach.coach_id', $report->coach_id);
                                });
                        })
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if (count($candidates) !== 1) {
                        continue;
                    }

                    $sessionId = $candidates[0];

                    if (isset($claimed[$sessionId])) {
                        continue;
                    }

                    DB::table('reports')
                        ->where('id', $report->id)
                        ->update(['teaching_schedule_id' => $sessionId]);

                    $claimed[$sessionId] = true;
                }
            });
    }
};
