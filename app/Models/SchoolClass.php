<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $table = 'classes'; // nama tabel tetap 'classes' di database

    protected $fillable = ['school_id', 'name'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    // reports.class_id memakai FK RESTRICT, jadi relasi ini dipakai untuk
    // memeriksa apakah kelas masih boleh dihapus.
    public function reports()
    {
        return $this->hasMany(Report::class, 'class_id');
    }

    public function coachAssignments()
    {
        return $this->hasMany(CoachClass::class, 'class_id');
    }

    /**
     * Jadwal mengajar kelas ini. Dipakai School Workspace untuk memastikan
     * kelas ber-jadwal tidak dipindah/dihapus sembarangan (school_id pada
     * jadwal historis tidak boleh bertentangan dengan school_id kelas).
     */
    public function teachingSchedules()
    {
        return $this->hasMany(TeachingSchedule::class, 'class_id');
    }

    public function programClasses()
    {
        return $this->hasMany(ProgramClass::class, 'class_id');
    }

    public function programs()
    {
        return $this->belongsToMany(
            Program::class,
            'program_classes',
            'class_id',
            'program_id'
        )->withTimestamps();
    }

    /**
     * Kelas yang boleh dilaporkan seorang coach: penugasan permanen
     * (`coach_classes`) ATAU penugasan sementara lewat sesi mengajar
     * (`teaching_schedules`, sebagai coach utama maupun tambahan).
     *
     * Dipakai dropdown "buat/edit laporan" supaya coach tambahan yang belum
     * punya `coach_classes` tetap bisa memilih kelas yang benar-benar dia ajar.
     */
    public function scopeReportableBy($query, int $coachId)
    {
        return $query->where(function ($q) use ($coachId): void {
            $q->whereHas('coachAssignments', fn ($c) => $c->where('coach_id', $coachId))
                ->orWhereHas('teachingSchedules', fn ($s) => $s->forCoach($coachId));
        });
    }
}
