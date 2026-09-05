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

    /**
     * Get the public/accessible URL for this media.
     *
     * For legacy Cloudinary URLs (path starts with http), returns as-is.
     * For local files, returns a signed route that enforces authorization.
     */
    public function url()
    {
        // Legacy Cloudinary URLs — return directly
        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }

        // Local files — use authorized serve route
        return route('media.serve', ['media' => $this->id]);
    }

    /**
     * Check whether this media is stored externally (e.g. Cloudinary).
     */
    public function isExternal(): bool
    {
        return str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://');
    }
}