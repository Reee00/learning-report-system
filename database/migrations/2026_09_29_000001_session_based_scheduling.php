<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penjadwalan berbasis SESI (refactor 2026-09-29).
 *
 * Sebelumnya TANGGAL setiap pertemuan adalah turunan: pola (hari + sekolah +
 * tanggal mulai) digenerate mingguan oleh ScheduleTemplateService, sehingga
 * `session_date` = start_date + 7k. Akibatnya operator tidak bisa mengisi
 * tanggal pertemuan secara bebas (10 Agu, 24 Agu, 31 Agu, 14 Sep, ...), dan
 * setiap generate ulang menyusun ulang nomor pertemuan berdasarkan tanggal —
 * reschedule satu sesi bisa diam-diam menggeser `meeting_number` sesi lain.
 *
 * Arah baru: SESI adalah record operasional yang otoritatif, tanggalnya
 * DIISI MANUAL dan boleh diubah kapan saja. Pola turun peran menjadi metadata
 * preferensi (hari favorit, jam, coach, target jumlah pertemuan).
 *
 * Tiga perubahan, semuanya aditif dan aman untuk data lama:
 *
 * 1. `teaching_schedules.session_date` -> NULLABLE. Tanggal boleh kosong =
 *    "Belum dijadwalkan": sesi sudah masuk rencana (mis. Pertemuan 7) tetapi
 *    tanggalnya belum ditetapkan. Sesi tanpa tanggal tidak dihitung sebagai
 *    tunggakan laporan karena belum pernah diajarkan.
 *
 * 2. `teaching_schedules.status` (scheduled|completed|postponed|cancelled).
 *    Sebelumnya satu-satunya penanda adalah `is_active` (aktif/nonaktif).
 *    Status baru ini menjawab pertanyaan operasional yang berbeda: sesi ini
 *    sudah terlaksana, ditunda, atau dibatalkan. `is_active` TETAP dipakai
 *    untuk "Inactive" (sesi tidak dioperasikan sama sekali) supaya seluruh
 *    aturan sesi nonaktif yang sudah berjalan tidak berubah.
 *
 * 3. `classes.target_meetings` — target jumlah pertemuan milik KELAS, dipakai
 *    untuk progress "3/10 pertemuan". Di-backfill dari jumlah `meeting_count`
 *    pola yang sudah ada; bila kelas belum punya pola, dipakai jumlah sesi
 *    yang sudah ada. NULL = target belum ditetapkan (bukan 0).
 *
 * Tidak ada tabel baru dan tidak ada kolom yang dibuang.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->date('session_date')->nullable()->change();
            $table->string('status', 20)->default('scheduled')->after('is_active');

            // Reminder menyaring sesi per status + rentang tanggal; daftar sesi
            // menyaring per kelas + status.
            $table->index(['status', 'session_date'], 'ts_status_date_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_meetings')->nullable()->after('name');
        });

        $this->backfillTargets();
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('target_meetings');
        });

        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->dropIndex('ts_status_date_idx');
            $table->dropColumn('status');
        });

        // Sesi tanpa tanggal tidak bisa dikembalikan ke NOT NULL tanpa
        // mengarang tanggal; baris seperti itu dihapus lebih dulu supaya
        // rollback tidak gagal di tengah jalan. Baris begini hanya mungkin
        // dibuat oleh fitur baru ini (fitur lama selalu mengisi tanggal).
        DB::table('teaching_schedules')->whereNull('session_date')->delete();

        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->date('session_date')->nullable(false)->change();
        });
    }

    /**
     * Target pertemuan per kelas dari pola yang sudah ada. Kelas tanpa pola
     * memakai jumlah sesinya sendiri; kelas tanpa keduanya dibiarkan NULL.
     */
    private function backfillTargets(): void
    {
        DB::table('classes')->orderBy('id')->chunkById(200, function ($classes): void {
            foreach ($classes as $class) {
                $target = (int) DB::table('teaching_schedule_templates')
                    ->where('class_id', $class->id)
                    ->sum('meeting_count');

                if ($target < 1) {
                    $target = (int) DB::table('teaching_schedules')
                        ->where('class_id', $class->id)
                        ->count();
                }

                if ($target > 0) {
                    DB::table('classes')->where('id', $class->id)
                        ->update(['target_meetings' => $target]);
                }
            }
        });
    }
};
