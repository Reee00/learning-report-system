<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA M-004: drop the legacy reports.photo_path column. Verified unused —
 * all media flows through report_media since MediaStorageService was
 * introduced. Column is nullable and never written, so dropping it loses
 * no data.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->after('notes');
        });
    }
};
