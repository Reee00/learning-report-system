<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportAttendance extends Model
{
    public $timestamps = false;
    protected $fillable = ['report_id', 'student_id', 'status'];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}