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

    public function coachAssignments()
    {
        return $this->hasMany(CoachClass::class, 'class_id');
    }
}