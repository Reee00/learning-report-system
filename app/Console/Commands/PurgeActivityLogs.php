<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Hapus activity log yang lebih tua dari 7 hari (audit keamanan
 * 2026-09-11). Dijadwalkan harian lewat scheduler; hanya menyentuh
 * tabel activity_logs — data aplikasi/bisnis tidak pernah dihapus.
 */
class PurgeActivityLogs extends Command
{
    protected $signature = 'activity-logs:purge {--days=7 : Retensi dalam hari}';

    protected $description = 'Delete activity logs older than the retention period (default 7 days)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} activity log entries older than {$days} day(s).");

        return self::SUCCESS;
    }
}
