<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert the legacy admin role to Relation and make the role column
     * extensible for the approved role architecture.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 50)->change();
        });

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'relation']);
    }

    /**
     * Rollback is intentionally guarded so role data is not silently lost.
     */
    public function down(): void
    {
        $hasUnsupportedRoles = DB::table('users')
            ->whereNotIn('role', ['admin', 'relation', 'coach', 'school_pic'])
            ->exists();

        if ($hasUnsupportedRoles) {
            throw new RuntimeException(
                'Cannot rollback role migration while expanded roles are in use.'
            );
        }

        DB::table('users')
            ->where('role', 'relation')
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'coach', 'school_pic'])->change();
        });
    }
};
