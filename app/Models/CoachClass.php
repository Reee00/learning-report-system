<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachClass extends Model
{
    public $timestamps = false;
    protected $table = 'coach_classes';
    protected $fillable = ['coach_id', 'class_id'];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}