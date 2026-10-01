<?php

namespace App\Models;

use NotificationChannels\WebPush\PushSubscription as BasePushSubscription;

/**
 * Subscription Web Push per perangkat (PWA Phase 2).
 *
 * Satu user boleh punya BANYAK baris di sini (HP, tablet, desktop). Tabel dan
 * koneksinya diambil dari config('webpush.*') oleh model induk, sehingga model
 * ini hanya menambahkan titik masuk milik aplikasi tanpa mengubah perilaku paket.
 *
 * Model ini TIDAK menyimpan rahasia apa pun: endpoint + public key + auth token
 * memang harus disimpan server-side (dibutuhkan untuk mengenkripsi payload),
 * tetapi nilainya tidak pernah dikembalikan ke klien.
 */
class PushSubscription extends BasePushSubscription
{
    //
}
