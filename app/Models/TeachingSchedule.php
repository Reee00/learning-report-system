<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TeachingSchedule extends Model
{
    protected $fillable = [
        'template_id', 'meeting_number',
        'school_id', 'class_id', 'program_id', 'coach_id',
        'session_date', 'day_of_week', 'student_count',
        'start_time', 'end_time', 'tools_dk', 'tools_rk',
        'jalan_minggu_ini', 'is_active', 'keterangan', 'topic',
        'departure_location', 'departure_time', 'arrival_time',
    ];

    protected $casts = [
        'template_id'      => 'integer',
        'meeting_number'   => 'integer',
        'session_date'     => 'date',
        'day_of_week'      => 'integer',
        'student_count'    => 'integer',
        'start_time'       => 'datetime:H:i',
        'end_time'         => 'datetime:H:i',
        'jalan_minggu_ini' => 'boolean',
        'is_active'        => 'boolean',
        'departure_time'   => 'datetime:H:i',
        'arrival_time'     => 'datetime:H:i',
    ];

    protected static function booted(): void
    {
        // Denormalized weekday (ISO 1=Mon..7=Sun) so the "day" filter works
        // identically on MySQL and SQLite.
        static::saving(function (TeachingSchedule $schedule): void {
            if ($schedule->session_date !== null) {
                $schedule->day_of_week = $schedule->session_date->isoWeekday();
            }
        });
    }

    public function template()
    {
        return $this->belongsTo(TeachingScheduleTemplate::class, 'template_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Coach tambahan (co-teacher). Coach utama tetap di kolom coach_id —
     * kepemilikan laporan dan reminder mengacu pada coach utama.
     */
    public function additionalCoaches()
    {
        return $this->belongsToMany(User::class, 'teaching_schedule_coach', 'schedule_id', 'coach_id')
            ->withTimestamps();
    }

    /**
     * Sesi yang benar-benar diajarkan. Dipakai reminder laporan dan daftar
     * sesi aktif; sesi nonaktif tetap ada di database tetapi tidak dihitung.
     */
    public function scopeActive($query)
    {
        return $query->where('teaching_schedules.is_active', true);
    }

    /**
     * Sesi yang melibatkan seorang coach — sebagai coach utama (coach_id)
     * maupun coach tambahan (pivot teaching_schedule_coach).
     *
     * Ini satu-satunya definisi "coach terlibat pada sesi", dipakai bersama
     * oleh scope daftar jadwal, reminder, dan izin menulis laporan supaya
     * ketiganya tidak pernah berbeda pendapat.
     */
    public function scopeForCoach($query, int $coachId)
    {
        return $query->where(function ($q) use ($coachId): void {
            $q->where('teaching_schedules.coach_id', $coachId)
                ->orWhereHas('additionalCoaches', fn ($c) => $c->where('coach_id', $coachId));
        });
    }

    /**
     * Semua coach pada sesi ini: coach utama + coach tambahan.
     */
    public function allCoaches(): Collection
    {
        return collect([$this->coach])
            ->filter()
            ->merge($this->additionalCoaches)
            ->values();
    }

    /**
     * Kolom jam selalu disimpan sebagai "HH:MM:SS" agar perbandingan
     * whereTime konsisten antara MySQL dan SQLite (strftime).
     */
    protected function normalizeTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return $value;
    }

    protected function setStartTimeAttribute(mixed $value): void
    {
        $this->attributes['start_time'] = $this->normalizeTimeValue($value);
    }

    protected function setEndTimeAttribute(mixed $value): void
    {
        $this->attributes['end_time'] = $this->normalizeTimeValue($value);
    }

    protected function setDepartureTimeAttribute(mixed $value): void
    {
        $this->attributes['departure_time'] = $this->normalizeTimeValue($value);
    }

    protected function setArrivalTimeAttribute(mixed $value): void
    {
        $this->attributes['arrival_time'] = $this->normalizeTimeValue($value);
    }
}
