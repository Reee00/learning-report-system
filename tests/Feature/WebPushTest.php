<?php

namespace Tests\Feature;

use App\Jobs\SendWebPush;
use App\Models\CoachClass;
use App\Models\Program;
use App\Models\PushSubscription;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Notifications\CustomNotification;
use App\Notifications\ReportReminderNotification;
use App\Services\CustomNotificationService;
use App\Services\ReportReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\WebPush;
use RuntimeException;
use Tests\Support\FakeWebPush;
use Tests\Support\ImmediatePushProbe;
use Tests\TestCase;
use Throwable;

/**
 * PWA Phase 2 — Web Push sebagai kanal pengiriman TAMBAHAN.
 *
 * Yang dijaga pengujian ini:
 *
 *   1. SUBSCRIPTION — satu user boleh punya banyak perangkat; perangkat hanya
 *      bisa didaftarkan/dihapus oleh pemiliknya; `user_id` dari klien diabaikan;
 *      endpoint divalidasi anti-SSRF.
 *   2. SATU NOTIFIKASI — satu `notify()` tetap menghasilkan TEPAT SATU baris
 *      `notifications` (sumber kebenaran) + N pengiriman push (satu per
 *      perangkat). Push tidak pernah membuat record kedua.
 *   3. BEST-EFFORT — kegagalan push (penyedia mati, VAPID kosong, perangkat
 *      kedaluwarsa) tidak pernah menggagalkan notifikasi database, tidak
 *      melempar exception ke pemanggil, dan tidak membuat request 500.
 *   4. CLEANUP — hanya perangkat yang dilaporkan kedaluwarsa (404/410) yang
 *      dibuang; perangkat lain tidak tersentuh.
 *   5. KEAMANAN — private key VAPID tidak pernah sampai ke klien, payload push
 *      tidak memuat rahasia, dan service worker tidak menyimpan data notifikasi.
 *
 * Pengiriman jaringan diganti FakeWebPush (tests/Support/FakeWebPush.php):
 * yang diuji adalah keputusan kanal, bukan kriptografi pustaka Minishlink.
 */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    /** Endpoint push palsu yang lolos validasi (https, hostname publik). */
    private const DEVICE_A = 'https://fcm.googleapis.com/fcm/send/device-aaa';
    private const DEVICE_B = 'https://updates.push.services.mozilla.com/wpush/v2/device-bbb';
    private const DEVICE_C = 'https://wns2-par02p.notify.windows.com/w/?token=device-ccc';

    /** Perangkat milik coach LAIN: tidak boleh pernah menerima push coach ini. */
    private const DEVICE_OTHER = 'https://fcm.googleapis.com/fcm/send/device-other';

    private const FAKE_KEY = 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM';

    private School $school;
    private SchoolClass $schoolClass;
    private Program $program;
    private User $coach;
    private User $otherCoach;
    private User $relation;
    private FakeWebPush $push;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Push']);
        $this->schoolClass = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 1A']);
        $this->program = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        $this->coach = $this->makeUser('coach.push@test.test', User::ROLE_COACH);
        $this->otherCoach = $this->makeUser('coach.other@test.test', User::ROLE_COACH);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->schoolClass->id]);

        $this->relation = $this->makeUser('relation.push@test.test', User::ROLE_RELATION);

        // Push dianggap aktif: kunci VAPID diisi nilai contoh (FakeWebPush tidak
        // pernah memakainya untuk kriptografi), lalu WebPush diganti fake.
        $this->configureVapid();
        $this->bindFakePush();
    }

    // =========================================================
    // 1. Manajemen subscription
    // =========================================================

    public function test_authenticated_user_can_register_a_device(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A))
            ->assertOk()
            ->assertJson(['status' => 'subscribed']);

        $subscription = PushSubscription::firstOrFail();

        $this->assertSame(self::DEVICE_A, $subscription->endpoint);
        $this->assertSame($this->coach->id, (int) $subscription->subscribable_id);
        $this->assertSame(User::class, $subscription->subscribable_type);
        // Kunci enkripsi perangkat tersimpan server-side, tidak dibalas ke klien.
        $this->assertSame('p256dh-key', $subscription->public_key);
        $this->assertSame('auth-token', $subscription->auth_token);
    }

    public function test_registering_the_same_device_twice_updates_instead_of_duplicating(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A));

        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A, [
                'keys' => ['p256dh' => 'p256dh-key-rotated', 'auth' => 'auth-token-rotated'],
            ]))
            ->assertOk();

        $this->assertSame(1, PushSubscription::count(), 'Perangkat yang sama tidak boleh menghasilkan baris ganda.');
        $this->assertSame('p256dh-key-rotated', PushSubscription::firstOrFail()->public_key);
    }

    public function test_user_can_register_multiple_devices(): void
    {
        foreach ([self::DEVICE_A, self::DEVICE_B, self::DEVICE_C] as $endpoint) {
            $this->actingAs($this->coach)
                ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload($endpoint))
                ->assertOk();
        }

        $this->assertSame(3, $this->coach->pushSubscriptions()->count());
        $this->assertSame(3, PushSubscription::count());
    }

    public function test_device_is_reassigned_when_another_account_uses_the_same_browser(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A));

        // Akun lain login di browser yang sama: endpoint yang sama tidak boleh
        // tetap terdaftar pada akun lama, kalau tidak notifikasi akun lama akan
        // terus muncul di perangkat bersama.
        $this->actingAs($this->otherCoach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A))
            ->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($this->otherCoach->id, (int) PushSubscription::firstOrFail()->subscribable_id);
    }

    public function test_authenticated_user_can_unsubscribe_their_device(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A));

        $this->actingAs($this->coach)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => self::DEVICE_A])
            ->assertOk()
            ->assertJson(['status' => 'unsubscribed']);

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_unsubscribe_is_idempotent_for_an_unknown_endpoint(): void
    {
        $this->actingAs($this->coach)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => self::DEVICE_B])
            ->assertOk();
    }

    public function test_a_user_cannot_unsubscribe_another_users_device(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A));

        $this->actingAs($this->otherCoach)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => self::DEVICE_A])
            ->assertOk();

        $this->assertSame(1, PushSubscription::count(), 'Perangkat milik user lain tidak boleh terhapus.');
        $this->assertSame($this->coach->id, (int) PushSubscription::firstOrFail()->subscribable_id);
    }

    public function test_subscription_endpoints_require_authentication(): void
    {
        $this->post(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A))
            ->assertRedirect(route('login'));

        $this->delete(route('push-subscriptions.destroy'), ['endpoint' => self::DEVICE_A])
            ->assertRedirect(route('login'));

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_subscription_endpoints_require_a_csrf_token(): void
    {
        // Tanpa perlindungan CSRF, situs lain bisa memaksa browser korban
        // mendaftarkan perangkat penyerang lewat form lintas situs.
        //
        // Laravel melewati pemeriksaan CSRF saat environment-nya "testing",
        // jadi environment-nya digeser dulu supaya pemeriksaan sungguhan
        // benar-benar berjalan di test ini.
        $this->app['env'] = 'local';

        // Klien asli (partials/push-notifications-scripts) selalu mengirim
        // Accept: application/json + X-Requested-With, jadi permintaan dengan
        // token basi harus dijawab JSON 419 — bukan halaman HTML — supaya
        // skrip bisa membedakan "token kedaluwarsa" dari kegagalan lain.
        $this->withMiddleware()
            ->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A))
            ->assertStatus(419);

        // POST tanpa header JSON — mis. form biasa yang tokennya kedaluwarsa —
        // tidak lagi mendapat halaman "Page Expired" mentah. Sesuai perbaikan
        // UX 419 di bootstrap/app.php, user dikembalikan ke halaman asal
        // dengan pesan yang bisa dibaca dan isian yang masih utuh.
        $this->withMiddleware()
            ->actingAs($this->coach)
            ->from(route('account.edit'))
            ->post(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A))
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('error');

        // Apa pun bentuk jawabannya, yang terpenting: tidak ada perangkat
        // yang terdaftar.
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_client_supplied_user_id_is_never_trusted(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload(self::DEVICE_A, [
                // Upaya mendaftarkan perangkat atas nama orang lain.
                'user_id' => $this->otherCoach->id,
                'subscribable_id' => $this->otherCoach->id,
                'id' => $this->otherCoach->id,
            ]))
            ->assertOk();

        $this->assertSame(
            $this->coach->id,
            (int) PushSubscription::firstOrFail()->subscribable_id,
            'Pemilik subscription harus selalu diambil dari user yang login.'
        );
    }

    /**
     * Endpoint push adalah URL yang nanti di-POST oleh server. Tanpa validasi,
     * user terautentikasi bisa memakainya untuk memindai jaringan internal.
     */
    public function test_endpoint_validation_rejects_non_push_urls(): void
    {
        $hostile = [
            'http://fcm.googleapis.com/fcm/send/x',            // bukan https
            'https://169.254.169.254/latest/meta-data/',       // metadata cloud
            'https://127.0.0.1:8080/internal',                 // loopback
            'https://localhost/internal',                      // host tanpa titik
            'https://10.0.0.5/push',                           // IP privat
            'https://fcm.googleapis.com:8443/fcm/send/x',      // port non-standar
            'file:///etc/passwd',                              // skema lain
            '//fcm.googleapis.com/fcm/send/x',                 // protokol-relatif
        ];

        foreach ($hostile as $endpoint) {
            $this->actingAs($this->coach)
                ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload($endpoint))
                ->assertStatus(422)
                ->assertJsonValidationErrors('endpoint');
        }

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_subscription_requires_endpoint_and_keys(): void
    {
        $this->actingAs($this->coach)
            ->postJson(route('push-subscriptions.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    // =========================================================
    // 2. Integrasi notifikasi — satu notifikasi logis
    // =========================================================

    public function test_one_notification_writes_one_row_and_pushes_to_every_device(): void
    {
        $this->registerDevices([self::DEVICE_A, self::DEVICE_B, self::DEVICE_C]);

        $this->coach->notify(new CustomNotification(
            senderName: 'Relation Satu',
            senderRole: 'Relation',
            title: 'Perubahan Jadwal',
            message: 'Jadwal besok dimajukan 30 menit.',
            actionUrl: '/coach/reports',
            type: 'schedule',
        ));

        // SUMBER KEBENARAN: tepat satu baris notifikasi database.
        $this->assertSame(1, DB::table('notifications')->count(), 'Push tidak boleh membuat notifikasi kedua.');

        // KANAL TAMBAHAN: satu pengiriman per perangkat.
        $this->assertSame(3, count($this->push->queued));
        $this->assertEqualsCanonicalizing(
            [self::DEVICE_A, self::DEVICE_B, self::DEVICE_C],
            $this->push->queuedEndpoints()
        );
    }

    public function test_push_payload_carries_the_database_notification_id(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        $this->coach->notify(new CustomNotification(
            senderName: 'Relation Satu',
            senderRole: 'Relation',
            title: 'Perubahan Jadwal',
            message: 'Jadwal besok dimajukan 30 menit.',
            actionUrl: '/coach/reports',
            type: 'schedule',
        ));

        $rowId = (string) DB::table('notifications')->value('id');
        $payload = $this->push->payloadFor(self::DEVICE_A);

        $this->assertNotNull($payload);
        $this->assertSame('Perubahan Jadwal', $payload['title']);
        $this->assertSame('Jadwal besok dimajukan 30 menit.', $payload['body']);
        $this->assertSame('schedule', $payload['data']['type']);
        $this->assertSame($rowId, $payload['data']['notification_id'], 'ID push harus menunjuk baris database yang sama.');
        $this->assertSame('/coach/reports', $payload['data']['url']);
    }

    public function test_report_reminder_pushes_the_same_logical_notification(): void
    {
        $this->registerDevices([self::DEVICE_A, self::DEVICE_B]);
        $this->generateOverdueSchedule();

        $sent = app(ReportReminderService::class)->send($this->relation, $this->coach);

        $this->assertSame(1, $sent);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(2, count($this->push->queued));

        $payload = $this->push->payloadFor(self::DEVICE_A);
        $this->assertSame('Pengingat Laporan', $payload['title']);
        $this->assertSame('report_reminder', $payload['data']['type']);
        $this->assertSame('/coach/reports', $payload['data']['url']);
        $this->assertSame((string) DB::table('notifications')->value('id'), $payload['data']['notification_id']);
        // Isi pengingat ikut di body, sama persis dengan yang tampil di aplikasi.
        $this->assertStringContainsString('1 sesi mengajar', (string) $payload['body']);
    }

    /**
     * Payload push dibatasi: title, body, icon, badge + metadata. Tidak ada
     * kunci enkripsi perangkat, endpoint, token, atau isi laporan privat.
     */
    public function test_push_payload_never_contains_sensitive_data(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        $this->coach->notify(new ReportReminderNotification(
            senderName: 'Relation Satu',
            senderRole: 'Relation',
            missingCount: 4,
        ));

        $raw = (string) $this->push->queued[0]['payload'];

        foreach (['auth_token', 'auth-token', 'public_key', 'p256dh', 'endpoint', 'fcm.googleapis.com', 'password', 'remember_token'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $raw, "Payload push memuat data terlarang: {$forbidden}");
        }

        $payload = json_decode($raw, true);
        $this->assertSame(
            [],
            array_diff(array_keys($payload), ['title', 'body', 'icon', 'badge', 'tag', 'data']),
            'Payload push hanya boleh memuat field yang sudah ditentukan.'
        );
        $this->assertSame(
            [],
            array_diff(array_keys($payload['data']), ['type', 'notification_id', 'url']),
            'Metadata push hanya boleh memuat type, notification_id, dan url.'
        );
    }

    public function test_notification_without_devices_still_reaches_the_database(): void
    {
        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 2));

        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame([], $this->push->queued);
    }

    public function test_push_is_skipped_entirely_when_vapid_is_not_configured(): void
    {
        config([
            'webpush.vapid.public_key' => null,
            'webpush.vapid.private_key' => null,
        ]);

        $this->registerDevices([self::DEVICE_A]);

        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 2));

        $this->assertSame(1, DB::table('notifications')->count(), 'Notifikasi database tetap dibuat tanpa VAPID.');
        $this->assertSame([], $this->push->queued, 'Tanpa kunci VAPID tidak ada push yang boleh dikirim.');
    }

    public function test_only_the_targeted_coach_devices_receive_the_push(): void
    {
        $this->registerDevices([self::DEVICE_A], $this->coach);
        $this->registerDevices([self::DEVICE_B], $this->otherCoach);

        app(CustomNotificationService::class)->send(
            $this->relation,
            [$this->coach->id],
            'Perubahan Jadwal',
            'Hanya untuk coach A.',
            'schedule',
            '/coach/reports'
        );

        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame([self::DEVICE_A], $this->push->queuedEndpoints(), 'Perangkat coach lain tidak boleh dikirimi.');
    }

    // =========================================================
    // 3. Multi-device & pembersihan subscription kedaluwarsa
    // =========================================================

    public function test_expired_device_is_removed_while_other_devices_survive(): void
    {
        $this->registerDevices([self::DEVICE_A, self::DEVICE_B, self::DEVICE_C]);

        // Penyedia push menjawab 410 Gone hanya untuk perangkat A.
        $this->push->reports = [
            ['endpoint' => self::DEVICE_A, 'success' => false, 'status' => 410, 'reason' => 'Gone'],
            ['endpoint' => self::DEVICE_B, 'success' => true, 'status' => 201, 'reason' => 'Created'],
            ['endpoint' => self::DEVICE_C, 'success' => false, 'status' => 500, 'reason' => 'Internal Server Error'],
        ];

        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 1));

        $remaining = PushSubscription::pluck('endpoint')->all();

        $this->assertNotContains(self::DEVICE_A, $remaining, 'Perangkat kedaluwarsa (410) harus dibuang.');
        $this->assertContains(self::DEVICE_B, $remaining, 'Perangkat yang berhasil tidak boleh tersentuh.');
        $this->assertContains(
            self::DEVICE_C,
            $remaining,
            'Kegagalan sementara (500) tidak boleh menghapus perangkat.'
        );
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_expired_cleanup_never_deletes_another_users_subscription(): void
    {
        $this->registerDevices([self::DEVICE_A], $this->coach);
        // Perangkat lain (milik coach lain) dengan endpoint berbeda.
        $this->registerDevices([self::DEVICE_B], $this->otherCoach);

        $this->push->reports = [
            ['endpoint' => self::DEVICE_A, 'success' => false, 'status' => 404, 'reason' => 'Not Found'],
        ];

        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 1));

        $this->assertSame(0, $this->coach->pushSubscriptions()->count());
        $this->assertSame(1, $this->otherCoach->pushSubscriptions()->count(), 'Subscription user lain tidak boleh ikut terhapus.');
    }

    public function test_only_the_failing_device_is_logged(): void
    {
        Log::spy();

        $this->registerDevices([self::DEVICE_A, self::DEVICE_B]);

        $this->push->reports = [
            ['endpoint' => self::DEVICE_A, 'success' => true, 'status' => 201, 'reason' => 'Created'],
            ['endpoint' => self::DEVICE_B, 'success' => false, 'status' => 503, 'reason' => 'Service Unavailable'],
        ];

        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 1));

        // Log kegagalan tidak boleh membocorkan endpoint perangkat.
        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []): bool {
                return ! str_contains(json_encode($context), 'mozilla');
            })
            ->atLeast()->once();
    }

    // =========================================================
    // 4. Failure handling — best-effort, tidak pernah merusak sumber kebenaran
    // =========================================================

    public function test_push_transport_failure_does_not_break_notification_creation(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        $this->push->flushError = 'Koneksi ke penyedia push gagal.';

        // Tidak boleh melempar: notify() harus selesai normal.
        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 3));

        $this->assertSame(1, DB::table('notifications')->count(), 'Notifikasi database harus tetap ada.');
        $this->assertSame(1, $this->coach->unreadNotifications()->count());
    }

    public function test_push_failure_is_logged_without_leaking_secrets(): void
    {
        config(['webpush.vapid.private_key' => 'rahasia-vapid-jangan-bocor']);
        $this->registerDevices([self::DEVICE_A]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = json_encode([$event->message, $event->context]);
        });

        $this->push->flushError = 'Penyedia push tidak dapat dihubungi.';

        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 1));

        $this->assertNotEmpty($logged, 'Kegagalan push harus tercatat di log.');
        foreach ($logged as $line) {
            $this->assertStringNotContainsString('rahasia-vapid-jangan-bocor', (string) $line);
            $this->assertStringNotContainsString(self::DEVICE_A, (string) $line);
        }
    }

    public function test_reminder_deduplication_still_uses_database_notifications(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        $this->generateOverdueSchedule();

        $reminders = app(ReportReminderService::class);

        $this->assertSame(1, $reminders->send($this->relation, $this->coach));
        // Reminder kedua ditahan karena yang pertama belum dibaca — aturan lama
        // tidak berubah oleh kehadiran kanal push.
        $this->assertSame(0, $reminders->send($this->relation, $this->coach));

        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(1, count($this->push->queued), 'Push kedua tidak boleh dikirim untuk reminder yang ditahan.');
    }

    public function test_mark_as_read_behaviour_is_unchanged(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        $this->coach->notify(new ReportReminderNotification('Relation', 'Relation', 2));
        $notifications = $this->coach->notifications()->get();

        $this->assertCount(1, $notifications);
        $this->assertNull($notifications->first()->read_at);

        $this->actingAs($this->coach)
            ->post(route('coach.notifications.read', $notifications->first()->id))
            ->assertRedirect();

        $this->assertSame(0, $this->coach->unreadNotifications()->count());
        $this->assertSame(1, DB::table('notifications')->count(), 'Menandai dibaca tidak boleh menyentuh baris push mana pun.');
    }

    public function test_notification_history_is_preserved_alongside_push(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        $this->coach->notify(new CustomNotification('Relation', 'Relation', 'Judul', 'Isi pesan', '/coach/reports', 'operational'));

        $row = $this->coach->notifications()->firstOrFail();
        $data = $row->data;

        // Riwayat in-app (banner/history coach) tetap lengkap seperti sebelumnya.
        $this->assertSame('Judul', $data['title']);
        $this->assertSame('Isi pesan', $data['message']);
        $this->assertSame('Relation', $data['sender_name']);
        $this->assertSame('/coach/reports', $data['action_url']);
        $this->assertSame(CustomNotification::class, $row->type);
    }

    public function test_action_url_from_user_input_cannot_become_an_external_redirect(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        foreach (['https://jahat.example/phishing', '//jahat.example/phishing', 'javascript:alert(1)'] as $hostile) {
            $this->push->queued = [];

            $this->coach->notify(new CustomNotification(
                senderName: 'Relation',
                senderRole: 'Relation',
                title: 'Judul',
                message: 'Isi',
                actionUrl: $hostile,
                type: 'operational',
            ));

            $payload = $this->push->payloadFor(self::DEVICE_A);
            $this->assertSame('/coach/reports', $payload['data']['url'], "URL berbahaya harus ditolak: {$hostile}");
        }
    }

    // =========================================================
    // 5. Keamanan sisi klien — UI & service worker
    // =========================================================

    public function test_push_ui_is_rendered_only_when_vapid_is_configured(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('id="pushNotifyBtn"', false)
            ->assertSee('Aktifkan Notifikasi', false);

        config(['webpush.vapid.public_key' => null]);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertDontSee('id="pushNotifyBtn"', false);
    }

    public function test_vapid_private_key_is_never_sent_to_the_client(): void
    {
        config([
            'webpush.vapid.public_key' => self::FAKE_KEY,
            'webpush.vapid.private_key' => 'private-key-yang-tidak-boleh-bocor',
        ]);

        $response = $this->actingAs($this->coach)->get(route('coach.reports.index'))->assertOk();

        $response->assertDontSee('private-key-yang-tidak-boleh-bocor', false);
        // Public key memang harus ada — itu applicationServerKey saat subscribe.
        $response->assertSee(self::FAKE_KEY, false);
    }

    public function test_permission_is_never_requested_on_page_load(): void
    {
        $script = file_get_contents(resource_path('views/partials/push-notifications-scripts.blade.php'));

        // Hanya SATU tempat yang boleh memanggil prompt izin.
        $this->assertSame(
            1,
            substr_count($script, 'requestPermission'),
            'Prompt izin hanya boleh ada di satu tempat (aksi user).'
        );

        // ...dan tempat itu ada di dalam enable(), bukan di refresh() yang
        // dijalankan otomatis saat halaman dimuat.
        $enable = strpos($script, 'function enable(');
        $disable = strpos($script, 'function disable(');
        $request = strpos($script, 'requestPermission');

        $this->assertNotFalse($enable);
        $this->assertNotFalse($disable);
        $this->assertGreaterThan($enable, $request);
        $this->assertLessThan($disable, $request);

        $refresh = substr($script, (int) strpos($script, 'function refresh('), (int) $enable - (int) strpos($script, 'function refresh('));
        $this->assertStringNotContainsString('requestPermission', $refresh, 'Memuat halaman tidak boleh memicu prompt izin.');

        // enable() hanya dijangkau lewat klik user.
        $this->assertStringContainsString("toggleBtn.addEventListener('click'", $script);
    }

    public function test_push_script_handles_the_denied_and_unsupported_states(): void
    {
        $script = file_get_contents(resource_path('views/partials/push-notifications-scripts.blade.php'));

        $this->assertStringContainsString("Notification.permission === 'denied'", $script);
        $this->assertStringContainsString('isSecureContext', $script);
        $this->assertStringContainsString("'PushManager' in window", $script);
        $this->assertStringContainsString("'serviceWorker' in navigator", $script);
        // Unsubscribe tersedia dari UI.
        $this->assertStringContainsString('unsubscribe()', $script);
    }

    public function test_service_worker_displays_push_and_opens_the_target_page(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString('showNotification', $sw);
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
        $this->assertStringContainsString('openWindow', $sw);
        $this->assertStringContainsString('includeUncontrolled: true', $sw);
    }

    public function test_service_worker_refuses_external_notification_targets(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Open-redirect dari payload push harus ditolak di sisi klien juga.
        $this->assertStringContainsString('safeTargetUrl', $sw);
        $this->assertStringContainsString("raw.charAt(1) === '/'", $sw);
        $this->assertStringContainsString('self.location.origin', $sw);
    }

    /**
     * "Do NOT cache authenticated notification data offline" — handler push
     * tidak boleh menyentuh Cache Storage sama sekali.
     */
    public function test_service_worker_does_not_cache_notification_data(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $pushHandler = substr($sw, (int) strpos($sw, "addEventListener('push'"));
        $pushHandler = substr($pushHandler, 0, (int) strpos($pushHandler, "addEventListener('notificationclick'"));

        $this->assertStringNotContainsString('caches.', $pushHandler);
        $this->assertStringNotContainsString('cache.put', $pushHandler);
        $this->assertStringNotContainsString('cache.add', $pushHandler);

        // '/notifications' hanya boleh muncul sebagai path terproteksi (yang
        // justru dilarang masuk cache), tidak pernah sebagai bahan precache.
        $this->assertStringNotContainsString("'/notifications", $this->precacheBlock($sw));
        foreach (['/notifications', '/coach', '/admin', '/reports'] as $prefix) {
            $this->assertStringStartsNotWith($prefix, implode('|', $this->precacheList($sw)));
        }
    }

    /** Potongan sumber yang memuat daftar PRECACHE_URLS saja. */
    private function precacheBlock(string $sw): string
    {
        $start = strpos($sw, 'const PRECACHE_URLS');
        $this->assertNotFalse($start);

        return substr($sw, (int) $start, (int) strpos($sw, '];', (int) $start) - (int) $start);
    }

    /** @return list<string> */
    private function precacheList(string $sw): array
    {
        $this->assertSame(1, preg_match('/const PRECACHE_URLS = \[(.*?)\];/s', $sw, $matches));

        preg_match_all("/const\s+([A-Z_][A-Z0-9_]*)\s*=\s*'([^']*)'/", $sw, $constants, PREG_SET_ORDER);
        $resolved = [];
        foreach ($constants as $constant) {
            $resolved[$constant[1]] = $constant[2];
        }

        // Pemisah '|' dipakai di atas untuk pemeriksaan prefix; entri di sini
        // dibaca apa adanya sebagai daftar URL.
        preg_match_all("/'([^']+)'/", $matches[1], $tokens);

        return array_map(
            fn (string $token) => $resolved[$token] ?? $token,
            $tokens[1]
        );
    }

    public function test_service_worker_badge_and_icon_are_public_assets(): void
    {
        $this->assertFileExists(public_path('icons/badge-72.png'));

        $size = getimagesize(public_path('icons/badge-72.png'));
        $this->assertNotFalse($size);
        $this->assertSame(72, $size[0]);
        $this->assertSame(72, $size[1]);

        // Badge di-precache supaya notifikasi tetap punya ikon saat koneksi buruk.
        $this->assertStringContainsString("'/icons/badge-72.png'", file_get_contents(public_path('sw.js')));
    }

    // =========================================================
    // Antrean (Phase 2 hardening)
    // =========================================================
    //
    // CATATAN PENTING TENTANG PENGUJIAN INI:
    //   CustomNotification dan ReportReminderNotification sama-sama
    //   `implements ShouldQueue`. Artinya `notify()` hanya MENITIPKAN
    //   notifikasi ke antrean; kanal — termasuk kanal push — baru berjalan saat
    //   worker mengeksekusinya. Dengan `Queue::fake()` job notifikasi itu ikut
    //   tertahan, sehingga kanal tidak pernah dipanggil.
    //
    //   Karena itu pengujian di bawah memakai notifikasi probe yang TIDAK
    //   mengantre (ImmediatePushProbe), supaya yang diuji benar-benar kanal
    //   push milik aplikasi: apa yang dijadwalkan, dengan ID apa, dan apa yang
    //   terjadi saat job-nya dijalankan worker. Integrasi dengan kedua
    //   notifikasi asli tetap diuji oleh blok pengujian sebelumnya.

    /**
     * Pengiriman push TIDAK boleh berjalan di dalam request pembuat notifikasi.
     * Yang terjadi saat `notify()` hanyalah penjadwalan job.
     */
    public function test_push_is_queued_instead_of_sent_inside_the_request(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Jadwal besok dimajukan 30 menit.'));

        // Sumber kebenaran tetap ditulis di dalam request.
        $this->assertSame(1, DB::table('notifications')->count());

        Queue::assertPushed(SendWebPush::class, 1);

        // Tidak ada satu pun panggilan HTTP ke penyedia push di dalam request.
        $this->assertSame([], $this->push->queued);
    }

    /**
     * Job harus membawa ID notifikasi logis yang SAMA dengan baris database.
     *
     * Objek notifikasi diserialisasi masuk ke payload antrean, jadi ini juga
     * membuktikan `$notification->id` selamat melewati serialize/unserialize.
     */
    public function test_queued_job_carries_the_same_notification_id(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Pesan uji.'));

        $rowId = (string) DB::table('notifications')->value('id');
        $job = $this->queuedPushJob();

        // Round-trip antrean sungguhan: apa yang dilihat worker.
        $restored = unserialize(serialize($job));

        $this->assertSame($rowId, (string) $restored->notification->id, 'ID notifikasi harus sama dengan baris database.');
        $this->assertSame($this->coach->getKey(), $restored->notifiableId);
        $this->assertSame($this->coach->getMorphClass(), $restored->notifiableType);
    }

    /** Worker mengirim push memakai ID notifikasi yang sama dengan baris database. */
    public function test_job_payload_id_matches_the_database_row(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Pesan uji.'));

        $rowId = (string) DB::table('notifications')->value('id');

        // Jalankan seperti worker: lewat round-trip antrean, bukan langsung.
        unserialize(serialize($this->queuedPushJob()))->handle();

        $payload = $this->push->payloadFor(self::DEVICE_A);

        $this->assertNotNull($payload);
        $this->assertSame($rowId, $payload['data']['notification_id']);
        $this->assertSame('Perubahan Jadwal', $payload['title']);
    }

    /** Perangkat dibaca ulang saat job jalan, bukan saat job dijadwalkan. */
    public function test_job_reads_devices_at_run_time(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Perangkat kedua baru mendaftar.'));

        // Perangkat B baru mendaftar SETELAH notifikasi dijadwalkan.
        $this->registerDevices([self::DEVICE_B]);

        unserialize(serialize($this->queuedPushJob()))->handle();

        $this->assertEqualsCanonicalizing(
            [self::DEVICE_A, self::DEVICE_B],
            $this->push->queuedEndpoints(),
            'Worker harus memakai daftar perangkat terbaru.'
        );
    }

    /** Perangkat yang dilepas sebelum job jalan tidak lagi dikirimi. */
    public function test_job_does_not_push_to_a_device_unsubscribed_before_it_runs(): void
    {
        $this->registerDevices([self::DEVICE_A, self::DEVICE_B]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Satu perangkat dilepas.'));

        $this->actingAs($this->coach)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => self::DEVICE_B])
            ->assertOk();

        unserialize(serialize($this->queuedPushJob()))->handle();

        $this->assertSame([self::DEVICE_A], $this->push->queuedEndpoints());
    }

    /** Penerima yang sudah dihapus: job berhenti diam-diam, bukan meledak. */
    public function test_job_stops_quietly_when_the_recipient_is_gone(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        $job = new SendWebPush(
            $this->coach->getMorphClass(),
            $this->coach->getKey(),
            new ImmediatePushProbe('X', 'Y'),
        );

        // Penerima hilang dari penyimpanan sebelum job dijalankan.
        $userId = $this->coach->getKey();
        $this->coach->delete();
        DB::table('push_subscriptions')->where('subscribable_id', $userId)->delete();

        $job->handle();

        $this->assertSame([], $this->push->queued);
    }

    /** Penerima tanpa perangkat: job selesai tanpa error dan tanpa kirim apa pun. */
    public function test_job_is_a_no_op_when_the_recipient_has_no_devices(): void
    {
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Tidak ada perangkat.'));

        unserialize(serialize($this->queuedPushJob()))->handle();

        $this->assertSame([], $this->push->queued);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    /** VAPID kosong: tidak ada job yang dibuat sama sekali. */
    public function test_no_job_is_queued_when_vapid_is_not_configured(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        config([
            'webpush.vapid.public_key' => '',
            'webpush.vapid.private_key' => '',
        ]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Push dimatikan.'));

        Queue::assertNothingPushed();
        $this->assertSame(1, DB::table('notifications')->count(), 'Notifikasi database tetap dibuat.');
    }

    /**
     * Kegagalan transport pada worker ditandai agar antrean bisa mencoba ulang,
     * tetapi TIDAK PERNAH merusak notifikasi database maupun melempar ke
     * pemanggil.
     */
    public function test_worker_transport_failure_is_retryable_but_never_breaks_the_request(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        $this->push->flushError = 'Penyedia push tidak dapat dihubungi.';

        // Sync queue: job berjalan di dalam request. Request tetap sukses.
        $this->coach->notify(new CustomNotification(
            senderName: 'Relation Satu',
            senderRole: 'Relation',
            title: 'Perubahan Jadwal',
            message: 'Penyedia push sedang mati.',
            actionUrl: '/coach/reports',
            type: 'schedule',
        ));

        $this->assertSame(1, DB::table('notifications')->count(), 'Sumber kebenaran harus tetap tersimpan.');

        // Job ditandai gagal (bukan ditelan) supaya antrean nyata bisa retry.
        $job = new SendWebPush(
            $this->coach->getMorphClass(),
            $this->coach->getKey(),
            new ImmediatePushProbe('X', 'Y'),
        );

        $this->expectException(Throwable::class);
        $job->handle();
    }

    /** Batas percobaan & jeda ditetapkan: job tidak bisa menggantung selamanya. */
    public function test_job_retry_policy_is_bounded(): void
    {
        $job = new SendWebPush('App\Models\User', 1, new ImmediatePushProbe('A', 'B'));

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
        $this->assertSame(30, $job->timeout);
    }

    /** `failed()` mencatat penyerahan tanpa membocorkan rahasia. */
    public function test_job_failure_handler_logs_without_leaking_secrets(): void
    {
        $this->configureVapid();
        Log::spy();

        $job = new SendWebPush(
            $this->coach->getMorphClass(),
            $this->coach->getKey(),
            new ImmediatePushProbe('Judul', 'Pesan'),
        );

        $job->failed(new RuntimeException('Penyedia push tidak dapat dihubungi.'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $encoded = json_encode($context);

                return str_contains($message, 'Web push')
                    && ! str_contains($encoded, 'vapid-private-key-untuk-pengujian')
                    && ! str_contains($encoded, 'fcm.googleapis.com');
            });
    }

    /** Antrean tidak boleh menyimpan objek model penerima (bisa jadi basi). */
    public function test_queued_job_stores_identity_not_a_model_instance(): void
    {
        $this->registerDevices([self::DEVICE_A]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Identitas, bukan model.'));

        $restored = unserialize(serialize($this->queuedPushJob()));

        $this->assertIsString($restored->notifiableType);
        $this->assertIsInt($restored->notifiableId);
        // Identitas saja: tidak ada model User (beserta atributnya) yang ikut
        // terserialisasi — kalau ikut, email penerima akan tampak di payload.
        $this->assertStringNotContainsString((string) $this->coach->email, serialize($restored));
        $this->assertStringNotContainsString('"password"', serialize($restored));
    }

    /**
     * Notifikasi push tidak boleh dibuat ulang oleh worker.
     *
     * Menjalankan job tidak pernah menambah baris `notifications`: job hanya
     * mengirim, tidak pernah menulis sumber kebenaran. Dijalankan dua kali
     * untuk menegaskan sifat at-least-once pengiriman push (boleh terkirim dua
     * kali), tetapi baris notifikasinya tetap satu.
     */
    public function test_running_the_job_never_creates_a_second_notification(): void
    {
        $this->registerDevices([self::DEVICE_A, self::DEVICE_B]);
        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Sekali saja.'));

        $this->assertSame(1, DB::table('notifications')->count());

        unserialize(serialize($this->queuedPushJob()))->handle();
        unserialize(serialize($this->queuedPushJob()))->handle();

        $this->assertSame(1, DB::table('notifications')->count(), 'Worker tidak boleh menduplikasi notifikasi.');
        // 2 perangkat x 2 eksekusi: push memang at-least-once, bukan exactly-once.
        $this->assertSame(4, count($this->push->queued));
    }

    /** Job mengambil perangkat dari penerima yang benar, bukan dari yang lain. */
    public function test_job_only_touches_the_queued_recipient(): void
    {
        $this->registerDevices([self::DEVICE_A]);

        $other = $this->makeUser('coach-lain@lrs.test', 'Coach');
        $this->registerDevices([self::DEVICE_OTHER], $other);

        Queue::fake();

        $this->coach->notify(new ImmediatePushProbe('Perubahan Jadwal', 'Hanya untuk coach ini.'));

        unserialize(serialize($this->queuedPushJob()))->handle();

        $this->assertSame([self::DEVICE_A], $this->push->queuedEndpoints());
    }
    // =========================================================
    // Helpers
    // =========================================================

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => 'User '.$email,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    /** Aktifkan kanal push dengan kunci VAPID contoh. */
    private function configureVapid(): void
    {
        config([
            'webpush.vapid.subject' => 'mailto:lrs@test.test',
            'webpush.vapid.public_key' => self::FAKE_KEY,
            'webpush.vapid.private_key' => 'vapid-private-key-untuk-pengujian',
        ]);
    }

    private function bindFakePush(): void
    {
        $this->push = new FakeWebPush;
        $this->app->instance(WebPush::class, $this->push);
    }

    /**
     * Job pengiriman push yang tertahan di antrean palsu.
     *
     * Dipakai bersama `unserialize(serialize(...))` supaya job dijalankan
     * persis seperti worker: lewat round-trip serialisasi payload antrean.
     */
    private function queuedPushJob(): SendWebPush
    {
        $pushed = Queue::pushedJobs()[SendWebPush::class] ?? [];

        $this->assertCount(1, $pushed, 'Harus ada tepat satu job pengiriman push.');

        return $pushed[0]['job'];
    }

    /**
     * Payload subscription seperti yang dikirim browser.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function subscriptionPayload(string $endpoint, array $overrides = []): array
    {
        return array_merge([
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
            'content_encoding' => 'aes128gcm',
        ], $overrides);
    }

    /** Daftarkan perangkat lewat endpoint HTTP asli (menguji jalur sungguhan). */
    private function registerDevices(array $endpoints, ?User $user = null): void
    {
        foreach ($endpoints as $endpoint) {
            $this->actingAs($user ?? $this->coach)
                ->postJson(route('push-subscriptions.store'), $this->subscriptionPayload($endpoint))
                ->assertOk();
        }
    }

    /** Satu sesi mengajar yang sudah lewat & belum dilaporkan. */
    private function generateOverdueSchedule(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), [
                'days' => [
                    1 => [
                        'blocks' => [[
                            'school_id' => $this->school->id,
                            'start_date' => '2026-01-05',
                            'meeting_count' => 1,
                            'rows' => [[
                                'class_id' => $this->schoolClass->id,
                                'program_id' => $this->program->id,
                                'coach_id' => $this->coach->id,
                                'start_time' => '08:00',
                                'end_time' => '09:30',
                                'student_count' => 10,
                            ]],
                        ]],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        TeachingSchedule::query()->update(['session_date' => now()->subDay()->toDateString()]);
    }
}
