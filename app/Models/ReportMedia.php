<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportMedia extends Model
{
    protected $fillable = ['report_id', 'type', 'path', 'original_name', 'disk', 'file_size'];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    // Helper: cek apakah file ini foto
    public function isPhoto(): bool
    {
        return $this->type === 'photo';
    }

    // Helper: cek apakah file ini video
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    // Helper: cek apakah file ini bukti kehadiran
    public function isAttendance(): bool
    {
        return $this->type === 'attendance';
    }

    /**
     * Get the accessible URL for this media.
     *
     * Local files are served through the authorized route that enforces
     * per-report access checks.
     */
    public function url()
    {
        return route('media.serve', ['media' => $this->id]);
    }
}