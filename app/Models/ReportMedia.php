<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportMedia extends Model
{
    protected $fillable = ['report_id', 'type', 'path', 'original_name'];

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

    // Helper: ambil URL publik
public function url()
{
    // Kalau path sudah URL lengkap (Cloudinary), langsung return
    if (str_starts_with($this->path, 'http')) {
        return $this->path;
    }
    
    // Fallback untuk data lama yang masih pakai local storage
    return asset('storage/' . $this->path);
}
}