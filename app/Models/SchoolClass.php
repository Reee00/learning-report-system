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
}
