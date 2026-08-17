<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['name', 'code', 'description', 'status'];

    public function programClasses()
    {
        return $this->hasMany(ProgramClass::class, 'program_id');
    }

    public function classes()
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'program_classes',
            'program_id',
            'class_id'
        )->withTimestamps();
    }
}
