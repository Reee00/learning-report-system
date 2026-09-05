<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Abstraction layer for report media storage.
 *
 * All file operations go through Laravel's Filesystem abstraction so the
 * underlying provider (local, S3, etc.) can be swapped via config without
 * touching any business logic.
 */
class MediaStorageService
{
    /**
     * The disk name used for report media storage.
     * Configured in config/filesystems.php, defaults to 'report_media'.
     */
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.report_media_disk', 'report_media');
    }

    /**
     * Get the disk name being used.
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * Store an uploaded file and create the ReportMedia record.
     *
     * @return ReportMedia The created media record.
     */
    public function store(Report $report, UploadedFile $file, string $type): ReportMedia
    {
        $directory = $this->buildDirectory($report, $type);
        $filename  = $this->generateSafeFilename($file);

        $relativePath = $directory . '/' . $filename;

        // Store through Laravel Filesystem — no memory issues even for large files
        Storage::disk($this->disk)->putFileAs($directory, $file, $filename);

        Log::info('Media stored locally', [
            'report_id' => $report->id,
            'type'      => $type,
            'path'      => $relativePath,
            'size'      => $file->getSize(),
        ]);

        return ReportMedia::create([
            'report_id'     => $report->id,
            'type'          => $type,
            'path'          => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'disk'          => $this->disk,
            'file_size'     => $file->getSize(),
        ]);
    }

    /**
     * Delete a media file from disk and remove the database record.
     *
     * Returns true if the file was successfully deleted (or didn't exist).
     */
    public function delete(ReportMedia $media): bool
    {
        $deleted = true;

        // Only attempt disk deletion for local files (not Cloudinary URLs)
        if (!$this->isExternalUrl($media->path)) {
            $disk = $media->disk ?? $this->disk;

            if (Storage::disk($disk)->exists($media->path)) {
                $deleted = Storage::disk($disk)->delete($media->path);

                if (!$deleted) {
                    Log::warning('Failed to delete media file from disk', [
                        'media_id' => $media->id,
                        'path'     => $media->path,
                        'disk'     => $disk,
                    ]);
                }
            }

            // Clean up empty directories up the tree
            $this->cleanupEmptyDirectories($disk, dirname($media->path));
        }

        $media->delete();

        return $deleted;
    }

    /**
     * Delete all media files associated with a report.
     */
    public function deleteAllForReport(Report $report): void
    {
        $report->media->each(function (ReportMedia $media) {
            $this->delete($media);
        });
    }

    /**
     * Get the public URL for a media file.
     *
     * For external URLs (legacy Cloudinary), returns the URL as-is.
     * For local files, returns the storage URL.
     */
    public function url(ReportMedia $media): string
    {
        if ($this->isExternalUrl($media->path)) {
            return $media->path;
        }

        $disk = $media->disk ?? $this->disk;
        return Storage::disk($disk)->url($media->path);
    }

    /**
     * Get the full filesystem path for serving a file with authorization.
     *
     * Returns null if the file doesn't exist.
     */
    public function absolutePath(ReportMedia $media): ?string
    {
        if ($this->isExternalUrl($media->path)) {
            return null;
        }

        $disk = $media->disk ?? $this->disk;

        if (!Storage::disk($disk)->exists($media->path)) {
            return null;
        }

        return Storage::disk($disk)->path($media->path);
    }

    /**
     * Check whether a media path is an external URL (e.g. Cloudinary).
     */
    public function isExternalUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    /**
     * Build the directory path for storing media.
     *
     * Structure: reports/{year}/{report_id}/images/
     *            reports/{year}/{report_id}/videos/
     */
    private function buildDirectory(Report $report, string $type): string
    {
        $year      = $report->report_date?->format('Y') ?? now()->format('Y');
        $subfolder = $type === 'photo' ? 'images' : 'videos';

        return "reports/{$year}/{$report->id}/{$subfolder}";
    }

    /**
     * Generate a safe, unique filename that prevents path traversal.
     */
    private function generateSafeFilename(UploadedFile $file): string
    {
        $extension = $this->sanitizeExtension($file->getClientOriginalExtension());
        $timestamp = now()->format('Ymd_His');
        $random    = Str::random(8);

        // Completely new name: no user input in the actual stored filename
        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Sanitize file extension to prevent injection.
     */
    private function sanitizeExtension(string $extension): string
    {
        // Only allow alphanumeric characters in extensions
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        return strtolower($clean ?: 'bin');
    }

    /**
     * Remove empty directories up to the 'reports' root.
     */
    private function cleanupEmptyDirectories(string $disk, string $directory): void
    {
        // Safety: don't go above the reports directory
        while ($directory !== '.' && $directory !== '' && str_starts_with($directory, 'reports/')) {
            $files = Storage::disk($disk)->files($directory);
            $dirs  = Storage::disk($disk)->directories($directory);

            if (empty($files) && empty($dirs)) {
                Storage::disk($disk)->deleteDirectory($directory);
                $directory = dirname($directory);
            } else {
                break;
            }
        }
    }
}
