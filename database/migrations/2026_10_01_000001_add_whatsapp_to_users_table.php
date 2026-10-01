<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor WhatsApp pribadi milik user (review meeting LRS 2026-10-01).
 *
 * Audit lebih dahulu: tabel `users` TIDAK punya kolom phone/whatsapp apa pun
 * (satu-satunya kolom kontak adalah `email`), jadi kolom baru memang
 * diperlukan. Kolom ini sengaja nullable tanpa default supaya data user yang
 * sudah ada tidak berubah dan tetap valid tanpa nomor.
 *
 * Yang DISIMPAN adalah nomor yang sudah dinormalisasi ke format lokal
 * `08xxxxxxxxxx` — lihat User::normalizeWhatsapp(). Normalisasi dilakukan di
 * sisi server, bukan hanya di form, supaya nilai dari API/request manual pun
 * konsisten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('whatsapp', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('whatsapp');
        });
    }
};
