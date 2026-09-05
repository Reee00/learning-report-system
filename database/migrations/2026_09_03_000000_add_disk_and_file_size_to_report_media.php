<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds disk and file_size columns to report_media table for local storage migration.
 *
 * - disk: identifies which filesystem disk stores the actual file (allows
 *   hybrid storage during migration from Cloudinary to local).
 * - file_size: cached file size in bytes for display and quota purposes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->string('disk', 50)->nullable()->after('original_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('disk');
        });
    }

    public function down(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->dropColumn(['disk', 'file_size']);
        });
    }
};
