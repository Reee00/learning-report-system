<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramClass extends Model
{
    protected $table = 'program_classes';

    protected $fillable = ['program_id', 'class_id'];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
