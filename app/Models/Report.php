<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    /** Laporan masih disusun coach dan belum masuk antrean review. */
    public const STATUS_DRAFT = 'draft';

    /** Laporan sudah dikirim coach dan menunggu review. */
    public const STATUS_SUBMITTED = 'submitted';

    /** Laporan sudah disetujui. */
    public const STATUS_APPROVED = 'approved';

    /** Laporan ditolak dan menunggu perbaikan coach. */
    public const STATUS_REJECTED = 'rejected';

    /**
     * Status yang membuat sebuah PERTEMUAN dianggap TERLAKSANA.
     *
     * Ini definisi tunggal yang dipakai bersama oleh reminder laporan
     * (ReportReminderService) dan penghitungan progress pertemuan
     * (SchoolClass::sessionProgressFor) supaya keduanya tidak pernah berbeda
     * angka. Laporan `rejected` belum dianggap terlaksana — pertemuannya
     * kembali dihitung setelah coach memperbaiki dan mengirim ulang.
     *
     * @var array<int, string>
     */
    public const COMPLETED_STATUSES = [self::STATUS_SUBMITTED, self::STATUS_APPROVED];

    protected $fillable = [
        'coach_id', 'school_id', 'class_id', 'teaching_schedule_id', 'report_date',
        'lesson_material', 'goals_materi', 'activity_report', 'notes',
        'status', 'admin_notes', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Sesi mengajar yang dilaporkan. Rujukan langsung inilah yang menegakkan
     * aturan SATU SESI = SATU LAPORAN; laporan lama (sebelum migrasi
     * teaching_schedule_id) boleh null dan tetap sah sebagai riwayat.
     */
    public function teachingSchedule()
    {
        return $this->belongsTo(TeachingSchedule::class, 'teaching_schedule_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendances()
    {
        return $this->hasMany(ReportAttendance::class);
    }

    /**
     * Batasi ke laporan milik satu sesi mengajar. Dipakai penegakan aturan
     * satu sesi = satu laporan dan oleh reminder.
     */
    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('reports.teaching_schedule_id', $sessionId);
    }
    public function media()
{
    return $this->hasMany(ReportMedia::class);
}

public function photos()
{
    return $this->hasMany(ReportMedia::class)->where('type', 'photo');
}

public function videos()
{
    return $this->hasMany(ReportMedia::class)->where('type', 'video');
}

/**
 * Bukti kehadiran (foto daftar hadir, dsb.) — disimpan pada arsitektur
 * media privat yang sama dengan foto/video laporan.
 */
public function attendanceMedia()
{
    return $this->hasMany(ReportMedia::class)->where('type', 'attendance');
}
}
