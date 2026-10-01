<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Satu SESI mengajar (refactor 2026-09-29: penjadwalan berbasis sesi).
 *
 * Sesi adalah record operasional yang otoritatif: `session_date` DIISI MANUAL
 * dan boleh diubah kapan saja, `meeting_number` adalah urutan pertemuan
 * ("Pertemuan 1, 2, 3, ...") dan TIDAK ikut berubah saat tanggal digeser.
 * Pola jadwal hanya menyediakan preferensi (hari, jam, coach, target) dan
 * tidak lagi menjadi sumber tanggal.
 */
class TeachingSchedule extends Model
{
    /** Sesi sudah dijadwalkan (default). */
    public const STATUS_SCHEDULED = 'scheduled';

    /** Sesi sudah terlaksana. */
    public const STATUS_COMPLETED = 'completed';

    /** Sesi ditunda — tanggalnya menunggu penetapan ulang. */
    public const STATUS_POSTPONED = 'postponed';

    /** Sesi dibatalkan (tidak dijalankan), riwayatnya tetap tersimpan. */
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Terjadwal',
        self::STATUS_COMPLETED => 'Terlaksana',
        self::STATUS_POSTPONED => 'Ditunda',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    /**
     * Status yang dianggap "berjalan": sesi berstatus ini yang dituntut
     * laporannya dan dihitung sebagai pertemuan kelas.
     */
    public const OPERATIONAL_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_COMPLETED,
        self::STATUS_POSTPONED,
    ];

    protected $fillable = [
        'template_id', 'meeting_number',
        'school_id', 'class_id', 'program_id', 'coach_id',
        'session_date', 'day_of_week', 'student_count',
        'start_time', 'end_time', 'tools_dk', 'tools_rk',
        'jalan_minggu_ini', 'is_active', 'status', 'keterangan', 'topic',
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
        // identically on MySQL and SQLite. Sesi yang tanggalnya dikosongkan
        // ("Belum dijadwalkan") tidak boleh menyimpan hari lama — kalau tidak,
        // filter hari akan menampilkan sesi seolah masih punya tanggal.
        static::saving(function (TeachingSchedule $schedule): void {
            $schedule->day_of_week = $schedule->session_date?->isoWeekday();
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
     * Laporan untuk sesi ini. Aturan bisnis: maksimum SATU laporan per sesi,
     * apa pun statusnya (rejected tetap memegang sesinya sampai dikoreksi
     * lewat alur resubmit). Ditegakkan indeks unik reports.teaching_schedule_id.
     */
    public function report()
    {
        return $this->hasOne(Report::class, 'teaching_schedule_id');
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
     * Sesi yang punya tanggal — kebalikan dari "Belum dijadwalkan".
     */
    public function scopeScheduled($query)
    {
        return $query->whereNotNull('teaching_schedules.session_date');
    }

    /**
     * Sesi yang belum punya tanggal: sudah masuk rencana pertemuan, tetapi
     * tanggalnya belum ditetapkan. Dipakai UI ("Belum dijadwalkan") dan
     * dipastikan tidak pernah muncul sebagai tunggakan laporan.
     */
    public function scopeUnscheduled($query)
    {
        return $query->whereNull('teaching_schedules.session_date');
    }

    /**
     * Sesi yang dihapus dari rencana operasional (dibatalkan).
     */
    public function scopeNotCancelled($query)
    {
        return $query->where('teaching_schedules.status', '!=', self::STATUS_CANCELLED);
    }

    /**
     * Label nomor pertemuan yang dipakai di seluruh UI dan label laporan.
     * Bukan "Week N": sesi tidak dianggap berjalan mingguan.
     */
    public function meetingLabel(): string
    {
        return $this->meeting_number !== null
            ? 'Pertemuan '.$this->meeting_number
            : 'Pertemuan belum bernomor';
    }

    /**
     * Status yang ditampilkan. "Inactive" menang atas status apa pun: sesi
     * yang dinonaktifkan memang tidak dioperasikan sama sekali.
     */
    public function displayStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        return $this->status ?? self::STATUS_SCHEDULED;
    }

    public function statusLabel(): string
    {
        return $this->displayStatus() === 'inactive'
            ? 'Inactive'
            : (self::STATUS_LABELS[$this->displayStatus()] ?? 'Terjadwal');
    }

    /**
     * Sesi sudah punya riwayat: laporan (beserta absensi dan medianya).
     * Sesi ber-riwayat tidak boleh dihapus/dibersihkan oleh aksi apa pun
     * yang bersifat konfigurasi (hapus pola, regenerate).
     */
    public function hasHistory(): bool
    {
        return static::whereKey($this->getKey())->withHistory()->exists();
    }

    /**
     * Sesi yang sudah punya RIWAYAT PEMBELAJARAN: ada laporan apa pun
     * statusnya — `draft`, `submitted`, `approved`, maupun `rejected`.
     *
     * Statusnya sengaja tidak disaring: laporan yang ditolak tetap membawa
     * absensi dan media, dan sesinya masih dipakai coach untuk memperbaiki
     * laporan itu. Jadi penghapusan sesi/pola harus berhenti pada laporan
     * berstatus apa pun — berbeda dari progress "terlaksana" yang hanya
     * menghitung `Report::COMPLETED_STATUSES`.
     *
     * Pencocokan laporan mengikuti aturan yang sama dengan reminder: tautan
     * langsung `reports.teaching_schedule_id`, dengan cadangan kelas + tanggal
     * untuk laporan lama yang belum punya tautan sesi.
     */
    public function scopeWithHistory($query)
    {
        return $query->whereExists(function ($sub): void {
            $sub->selectRaw('1')
                ->from('reports')
                ->where(function ($match): void {
                    $match->whereColumn('reports.teaching_schedule_id', 'teaching_schedules.id')
                        ->orWhere(function ($legacy): void {
                            $legacy->whereNull('reports.teaching_schedule_id')
                                ->whereColumn('reports.class_id', 'teaching_schedules.class_id')
                                ->whereColumn('reports.report_date', 'teaching_schedules.session_date');
                        });
                });
        });
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
