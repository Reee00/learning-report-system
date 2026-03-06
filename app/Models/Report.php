<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'coach_id', 'school_id', 'class_id', 'report_date',
        'lesson_material', 'activity_summary', 'notes',
        'photo_path', 'status', 'admin_notes', 'approved_by', 'approved_at',
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

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendances()
    {
        return $this->hasMany(ReportAttendance::class);
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
}