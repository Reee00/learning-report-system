<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal semester (refactor 2026-09-12): pisahkan TEMPLATE pola mingguan
 * berulang dari SESI aktual.
 *
 * - teaching_schedule_templates: pola per (periode, hari, sekolah, kelas,
 *   coach, jam) — mencerminkan workbook Excel semester perusahaan.
 * - teaching_schedule_template_coach: coach tambahan pada template.
 * - teaching_schedules tetap menjadi tabel SESI (dipakai report reminder,
 *   filter, visibility, multi-coach) dan mendapat kolom template_id +
 *   meeting_number untuk tautan balik ke template (nullable — sesi hasil
 *   CRUD/import lama tetap sah tanpa template).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('teaching_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('period_name', 150);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('meetings_target')->default(20);
            $table->unsignedTinyInteger('day_of_week'); // ISO 1=Senin..7=Minggu
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('student_count')->nullable();
            $table->string('tools_dk', 255)->nullable();
            $table->string('tools_rk', 255)->nullable();
            $table->boolean('jalan_minggu_ini')->default(true);
            $table->string('topic', 255)->nullable();
            $table->string('keterangan', 1000)->nullable();
            $table->string('departure_location', 100)->nullable();
            $table->time('departure_time')->nullable();
            $table->time('arrival_time')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'day_of_week']);
            $table->index(['coach_id', 'day_of_week']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('teaching_schedule_template_coach', function (Blueprint $table) {
            $table->foreignId('template_id')->constrained('teaching_schedule_templates')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['template_id', 'coach_id']);
        });

        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('id')
                ->constrained('teaching_schedule_templates')->nullOnDelete();
            $table->unsignedSmallInteger('meeting_number')->nullable()->after('template_id');
            $table->index(['template_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->dropIndex(['template_id']);
            $table->dropColumn(['template_id', 'meeting_number']);
        });
        Schema::dropIfExists('teaching_schedule_template_coach');
        Schema::dropIfExists('teaching_schedule_templates');
    }
};
