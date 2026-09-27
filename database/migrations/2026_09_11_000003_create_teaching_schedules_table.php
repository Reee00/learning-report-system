<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching schedule (meeting 2026-09 requirement E), imported through the
 * existing Excel workflow (FastExcel), one row per scheduled session.
 *
 * A session links a coach to a class at a school on a date. It is the basis
 * for schedule visibility and for report reminders: a session on or before
 * today without a matching report (coach_id + class_id + report_date) is
 * considered "not completed".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('teaching_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('topic', 255)->nullable();
            $table->timestamps();

            $table->index(['session_date']);
            $table->index(['coach_id', 'session_date']);
            $table->index(['school_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_schedules');
    }
};
