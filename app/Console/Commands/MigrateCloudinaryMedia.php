<?php

namespace App\Console\Commands;

use App\Models\ReportMedia;
use App\Services\MediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MigrateCloudinaryMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate-cloudinary {--limit=0 : Number of records to process (0 = all)} {--dry-run : Only show what would be done}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing Cloudinary media to local storage';

    /**
     * Execute the console command.
     */
    public function handle(MediaStorageService $mediaStorage)
    {
        $this->info('Starting Cloudinary media migration...');

        // Find media where path starts with http (Cloudinary legacy)
        $query = ReportMedia::where('path', 'like', 'http%');
        
        $totalToMigrate = $query->count();
        if ($totalToMigrate === 0) {
            $this->info('No Cloudinary media found to migrate.');
            return 0;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
            $totalToMigrate = min($totalToMigrate, $limit);
        }

        $this->info("Found {$totalToMigrate} media file(s) to migrate.");

        if ($this->option('dry-run')) {
            $this->info('Dry run mode enabled. No actual migration will occur.');
            foreach ($query->get() as $media) {
                $this->line("- [ID: {$media->id}] {$media->path}");
            }
            return 0;
        }

        $bar = $this->output->createProgressBar($totalToMigrate);
        $bar->start();

        $successCount = 0;
        $failCount = 0;
        $failedIds = [];

        foreach ($query->cursor() as $media) {
            try {
                $report = $media->report;
                if (!$report) {
                    throw new \Exception("Report not found for media ID {$media->id}");
                }

                // Download the file
                $response = Http::timeout(30)->get($media->path);
                
                if (!$response->successful()) {
                    throw new \Exception("HTTP request failed with status: {$response->status()}");
                }

                $content = $response->body();
                if (empty($content)) {
                    throw new \Exception("Downloaded empty file");
                }

                // Determine file extension
                $extension = 'bin';
                if ($media->original_name) {
                    $extension = pathinfo($media->original_name, PATHINFO_EXTENSION);
                }
                if (empty($extension)) {
                    $extension = $media->isPhoto() ? 'jpg' : 'mp4';
                }
                
                // Sanitize extension
                $extension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension)) ?: 'bin';

                // Build path using MediaStorageService logic
                $year = $report->report_date?->format('Y') ?? now()->format('Y');
                $subfolder = $media->isPhoto() ? 'images' : 'videos';
                $directory = "reports/{$year}/{$report->id}/{$subfolder}";
                
                $timestamp = now()->format('Ymd_His');
                $random    = Str::random(8);
                $filename  = "{$timestamp}_{$random}_migrated.{$extension}";
                
                $relativePath = $directory . '/' . $filename;
                $disk = $mediaStorage->getDisk();

                // Save to local storage
                Storage::disk($disk)->put($relativePath, $content);
                $fileSize = strlen($content);

                // Update database
                $media->update([
                    'path' => $relativePath,
                    'disk' => $disk,
                    'file_size' => $fileSize,
                ]);

                $successCount++;
            } catch (Throwable $e) {
                $failCount++;
                $failedIds[] = $media->id;
                $this->error("\nFailed ID {$media->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("Migration completed!");
        $this->info("Successfully migrated: {$successCount}");
        
        if ($failCount > 0) {
            $this->error("Failed to migrate: {$failCount}");
            $this->error("Failed Media IDs: " . implode(', ', $failedIds));
            $this->warn("The failed files still point to Cloudinary and remain accessible via the fallback mechanism.");
        }

        return $failCount === 0 ? 0 : 1;
    }
}
