<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status AKTIF / NONAKTIF per sesi mengajar (meeting 2026-09-27).
 *
 * Sekolah punya kalender akademik sendiri: satu pertemuan bisa jatuh pada
 * libur, ujian, atau pekan yang memang tidak dipakai. Sebelumnya satu-satunya
 * cara menyesuaikan adalah MENGHAPUS sesi, yang berarti kehilangan riwayat
 * nomor pertemuan dan tanggal aslinya.
 *
 * `is_active` memisahkan "sesi ini ada dalam pola" dari "sesi ini benar-benar
 * diajarkan". Sesi nonaktif tetap tersimpan utuh (meeting_number dan
 * session_date tidak berubah), tetapi tidak dihitung sebagai sesi mengajar
 * aktif sehingga tidak memicu reminder laporan dan tidak menuntut absensi.
 *
 * Default true supaya seluruh sesi yang sudah tergenerate tidak berubah
 * perilakunya setelah migrasi ini.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->boolean('is_active')->after('jalan_minggu_ini')->default(true);

            // Reminder menyaring sesi aktif per rentang tanggal; daftar sesi
            // menyaring per sekolah + status.
            $table->index(['is_active', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'session_date']);
            $table->dropColumn('is_active');
        });
    }
};
