<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->enum('type', ['photo', 'video']);
            $table->string('path', 255);
            $table->string('original_name', 255)->nullable(); // nama file asli
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_media');
    }
};