<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role', 'school_id'];

    protected $hidden = ['password', 'remember_token'];

    // Relasi: user bisa punya satu sekolah (untuk school_pic)
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    // Relasi: coach bisa punya banyak penugasan kelas
    public function coachClasses()
    {
        return $this->hasMany(CoachClass::class, 'coach_id');
    }
}