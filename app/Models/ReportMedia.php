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

    /**
     * URL unduh media: route yang SAMA dengan url(), hanya dengan penanda
     * `download` yang membuat MediaController mengirim Content-Disposition
     * attachment alih-alih inline.
     *
     * Sengaja tidak ada route kedua supaya otorisasi media tetap satu pintu:
     * file privat tidak pernah menjadi publik, dan siapa pun yang tidak boleh
     * MELIHAT media juga tidak boleh mengunduhnya.
     */
    public function downloadUrl()
    {
        return route('media.serve', ['media' => $this->id, 'download' => 1]);
    }
}