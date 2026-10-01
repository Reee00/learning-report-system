<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel antrean (PWA Phase 2 hardening).
 *
 * KENAPA MIGRASI INI ADA:
 *   .env.example sudah lama menyetel QUEUE_CONNECTION=database, dan
 *   CustomNotification/ReportReminderNotification sama-sama implements
 *   ShouldQueue — tetapi tabel `jobs` tidak pernah dibuat. Selama
 *   QUEUE_CONNECTION=sync di lokal, kekurangan itu tidak terlihat. Begitu
 *   antrean database benar-benar dipakai (produksi), SETIAP notifikasi gagal
 *   sebelum sempat ditulis: baris `notifications` tidak pernah dibuat dan
 *   notifikasi push tidak pernah dikirim.
 *
 *   Karena pengiriman Web Push sekarang dititipkan ke antrean, antrean yang
 *   tidak bisa jalan = fitur yang tidak bisa jalan. Tabel ini yang membuat
 *   QUEUE_CONNECTION=database benar-benar berfungsi.
 *
 * AMAN DIULANG:
 *   Masing-masing tabel dibuat hanya bila belum ada, sehingga aman di
 *   lingkungan yang tabelnya sudah dibuat manual (mis. lewat `queue:table`).
 *   Skemanya sama persis dengan keluaran bawaan `php artisan queue:table` dan
 *   `queue:failed-table` pada Laravel 12.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
