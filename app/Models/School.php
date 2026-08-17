<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = ['name', 'address', 'pic_name'];

    public function classes()
    {
        return $this->hasMany(SchoolClass::class, 'school_id');
    }

    // reports.school_id memakai FK RESTRICT, jadi relasi ini dipakai untuk
    // memeriksa apakah sekolah masih boleh dihapus.
    public function reports()
    {
        return $this->hasMany(Report::class, 'school_id');
    }

    /**
     * Students belong to a school through their assigned class.
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            SchoolClass::class,
            'school_id',
            'class_id',
            'id',
            'id'
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'school_user')
            ->withTimestamps();
    }
}
