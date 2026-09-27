<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pola jadwal per HARI + SEKOLAH (refactor 2026-09-25).
 *
 * Satu baris template = satu KELAS/SESI di dalam sebuah pola. Pola itu sendiri
 * adalah kelompok baris dengan (day_of_week, school_id, start_date) yang sama:
 * "SENIN — Penabur GS, mulai 10 Agustus 2026, 20 pertemuan".
 *
 * Setiap pola punya `start_date` dan `meeting_count` sendiri — TIDAK ADA
 * periode global. Dua sekolah pada hari yang sama boleh mulai di tanggal
 * berbeda, sehingga pertemuan ke-1 mereka juga berbeda.
 *
 * Sesi aktual tetap di teaching_schedules (template_id + meeting_number);
 * reminder laporan membaca sesi, bukan pola.
 */
class TeachingScheduleTemplate extends Model
{
    public const DAY_LABELS = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    public const DAY_LABELS_UPPER = [
        1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS',
        5 => 'JUMAT', 6 => 'SABTU', 7 => 'MINGGU',
    ];

    protected $fillable = [
        'pattern_name', 'start_date', 'meeting_count',
        'day_of_week', 'school_id', 'class_id', 'program_id', 'coach_id',
        'start_time', 'end_time', 'student_count', 'tools_dk', 'tools_rk',
        'jalan_minggu_ini', 'topic', 'keterangan',
        'departure_location', 'departure_time', 'arrival_time',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'meeting_count'    => 'integer',
        'day_of_week'      => 'integer',
        'student_count'    => 'integer',
        'start_time'       => 'datetime:H:i',
        'end_time'         => 'datetime:H:i',
        'jalan_minggu_ini' => 'boolean',
        'departure_time'   => 'datetime:H:i',
        'arrival_time'     => 'datetime:H:i',
    ];

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
     * Coach tambahan pada pola — disalin ke setiap sesi hasil generate.
     */
    public function additionalCoaches()
    {
        return $this->belongsToMany(User::class, 'teaching_schedule_template_coach', 'template_id', 'coach_id')
            ->withTimestamps();
    }

    /**
     * Sesi hasil generate (pertemuan ke-1..N) + sesi manual yang dilink
     * kemudian.
     */
    public function sessions()
    {
        return $this->hasMany(TeachingSchedule::class, 'template_id');
    }

    public function dayLabel(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? (string) $this->day_of_week;
    }

    public function dayLabelUpper(): string
    {
        return self::DAY_LABELS_UPPER[$this->day_of_week] ?? (string) $this->day_of_week;
    }

    /**
     * Tanggal pertemuan terakhir pola ini — turunan, bukan kolom.
     */
    public function endDate(): ?Carbon
    {
        if ($this->start_date === null) {
            return null;
        }

        return $this->start_date->copy()->addWeeks(max(1, (int) $this->meeting_count) - 1);
    }

    /**
     * Kunci pola: hari + sekolah + tanggal mulai. Dipakai untuk mengelompokkan
     * baris kelas menjadi satu blok sekolah pada tampilan dan aksi massal.
     */
    public function patternKey(): string
    {
        return $this->day_of_week.'|'.$this->school_id.'|'.$this->start_date?->toDateString();
    }

    /**
     * Filter satu pola (hari + sekolah + tanggal mulai) pada query apa pun.
     */
    public function scopePattern(Builder $query, int $day, int $schoolId, string $startDate): Builder
    {
        return $query->where('day_of_week', $day)
            ->where('school_id', $schoolId)
            ->whereDate('start_date', $startDate);
    }

    /**
     * Kolom jam selalu disimpan sebagai "HH:MM:SS" agar perbandingan
     * whereTime konsisten antara MySQL dan SQLite (strftime) — sama seperti
     * TeachingSchedule.
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

    /**
     * Kelompokkan koleksi template menjadi blok pola siap tampil:
     * satu entri per (hari + sekolah + tanggal mulai).
     *
     * @param  Collection<int, TeachingScheduleTemplate>  $templates
     * @return Collection<int, array<string, mixed>>
     */
    public static function groupIntoPatterns(Collection $templates): Collection
    {
        return $templates
            ->groupBy(fn (self $template) => $template->patternKey())
            ->map(function (Collection $group) {
                /** @var self $first */
                $first = $group->first();

                return [
                    'key'          => $first->patternKey(),
                    'day_of_week'  => (int) $first->day_of_week,
                    'day_label'    => $first->dayLabel(),
                    'school'       => $first->school,
                    'start_date'   => $first->start_date,
                    'end_date'     => $first->endDate(),
                    'meeting_count' => (int) $first->meeting_count,
                    'pattern_name' => $first->pattern_name,
                    'departure_location' => $first->departure_location,
                    'departure_time' => $first->departure_time,
                    'arrival_time' => $first->arrival_time,
                    'rows'         => $group->sortBy('start_time')->values(),
                ];
            })
            ->sortBy([['day_of_week', 'asc'], ['start_date', 'asc']])
            ->values();
    }
}
