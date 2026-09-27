<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching schedule management module (2026-09-11).
 *
 * Extends teaching_schedules from a bare import table into a managed session
 * record following the company's DIGISchool weekly schedule sheet concepts:
 * program, student count, tools, "jalan minggu ini" flag, notes, and
 * departure/arrival logistics. Multi-coach sessions keep the primary coach in
 * coach_id (report ownership + reminders) and store additional coaches in the
 * teaching_schedule_coach pivot.
 *
 * day_of_week (ISO: 1=Monday..7=Sunday) is denormalized from session_date on
 * model save so the "day" filter works identically on MySQL and SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->foreignId('program_id')->after('class_id')->nullable()
                ->constrained('programs')->restrictOnDelete();
            $table->unsignedTinyInteger('student_count')->after('session_date')->nullable();
            $table->string('tools_dk', 255)->after('end_time')->nullable();
            $table->string('tools_rk', 255)->after('tools_dk')->nullable();
            $table->boolean('jalan_minggu_ini')->after('tools_rk')->default(true);
            $table->text('keterangan')->after('jalan_minggu_ini')->nullable();
            $table->string('departure_location', 100)->after('keterangan')->nullable();
            $table->time('departure_time')->after('departure_location')->nullable();
            $table->time('arrival_time')->after('departure_time')->nullable();
            $table->unsignedTinyInteger('day_of_week')->after('session_date')->nullable();

            $table->index(['program_id']);
            $table->index(['class_id', 'session_date']);
        });

        Schema::create('teaching_schedule_coach', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('teaching_schedules')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['schedule_id', 'coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_schedule_coach');

        Schema::table('teaching_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropIndex(['class_id', 'session_date']);
            $table->dropIndex(['program_id']);
            $table->dropColumn([
                'student_count', 'tools_dk', 'tools_rk', 'jalan_minggu_ini',
                'keterangan', 'departure_location', 'departure_time',
                'arrival_time', 'day_of_week',
            ]);
        });
    }
};
