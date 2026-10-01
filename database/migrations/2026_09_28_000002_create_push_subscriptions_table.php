<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription Web Push per perangkat (PWA Phase 2).
 *
 * Satu user = banyak baris (multi-device). Endpoint WAJIB unik: satu perangkat
 * hanya boleh terhubung ke satu akun, sehingga saat akun lain login di browser
 * yang sama, baris lama dipindahkan (lihat HasPushSubscriptions::
 * updatePushSubscription milik paket — dipakai lewat PushSubscriptionController).
 *
 * Panjang endpoint 1024 char dengan charset ascii, bukan utf8mb4: endpoint push
 * (FCM/WNS) bisa melewati 500 karakter, dan indeks unik utf8mb4 1024 char akan
 * melewati batas 3072 byte MySQL/MariaDB. Charset ascii membuatnya 1024 byte.
 */
return new class extends Migration {
    public function up(): void
    {
        $tableName = config('webpush.table_name', 'push_subscriptions');
        $connection = config('webpush.database_connection');

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            // subscribable_type + subscribable_id: pemilik subscription (User).
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 1024)->charset('ascii')->unique();
            // Kunci enkripsi payload milik perangkat (p256dh + auth secret).
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))
            ->dropIfExists(config('webpush.table_name', 'push_subscriptions'));
    }
};
