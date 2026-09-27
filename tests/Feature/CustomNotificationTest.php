<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Composer notifikasi custom ke coach (audit UX 2026-09-11).
 *
 * Verifies:
 * - Relation (global) can notify any coach; coach receives it.
 * - PIC can only target coaches teaching in their plotted schools;
 *   cross-school targeting is rejected server-side.
 * - Coach can mark their notification as read (ownership enforced).
 * - Roles without notifications.send get 403 on the composer.
 */
class CustomNotificationTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coachA;
    private User $coachB;
    private User $relation;
    private User $picA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School A']);
        $this->schoolB = School::create(['name' => 'School B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas B']);

        $this->coachA = $this->makeUser('coach.a@test.test', User::ROLE_COACH);
        $this->coachB = $this->makeUser('coach.b@test.test', User::ROLE_COACH);
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);

        $this->relation = $this->makeUser('relation@test.test', User::ROLE_RELATION);
        $this->picA = $this->makeUser('pic.a@test.test', User::ROLE_SCHOOL_PIC);
        $this->picA->schools()->sync([$this->schoolA->id]);
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name'     => 'User ' . $email,
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    public function test_relation_can_notify_any_coach_globally(): void
    {
        Notification::fake();

        $this->actingAs($this->relation)
            ->post(route('admin.notifications.store'), [
                'type'      => 'schedule',
                'coach_ids' => [$this->coachA->id, $this->coachB->id],
                'title'     => 'Perubahan Jadwal',
                'message'   => 'Jadwal besok dimajukan 30 menit.',
            ])
            ->assertSessionHas('success');

        Notification::assertSentTo($this->coachA, \App\Notifications\CustomNotification::class);
        Notification::assertSentTo($this->coachB, \App\Notifications\CustomNotification::class);
    }

    public function test_pic_can_only_target_coaches_in_own_schools(): void
    {
        Notification::fake();

        // In-scope coach (teaches in School A) — allowed.
        $this->actingAs($this->picA)
            ->post(route('admin.notifications.store'), [
                'type'      => 'reminder',
                'coach_ids' => [$this->coachA->id],
                'title'     => 'Reminder Laporan',
                'message'   => 'Mohon segera kirim laporan.',
            ])
            ->assertSessionHas('success');

        // Cross-school coach (School B) — rejected by the service.
        $this->actingAs($this->picA)
            ->post(route('admin.notifications.store'), [
                'type'      => 'operational',
                'coach_ids' => [$this->coachB->id],
                'title'     => 'Warning',
                'message'   => 'Isi apa pun.',
            ])
            ->assertSessionHasErrors('coach_ids');

        Notification::assertSentTo($this->coachA, \App\Notifications\CustomNotification::class);
        Notification::assertNotSentTo($this->coachB, \App\Notifications\CustomNotification::class);
    }

    public function test_coach_receives_and_can_mark_notification_as_read(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.notifications.store'), [
                'type'      => 'operational',
                'coach_ids' => [$this->coachA->id],
                'title'     => 'Info',
                'message'   => 'Rapat evaluasi hari Jumat.',
            ]);

        $notification = $this->coachA->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at, 'Fresh notification must be unread.');
        $this->assertSame('Info', $notification->data['title']);

        // Another coach cannot mark it read (ownership check).
        $this->actingAs($this->coachB)
            ->post(route('coach.notifications.read', $notification->id))
            ->assertNotFound();

        // Owner marks it read.
        $this->actingAs($this->coachA)
            ->post(route('coach.notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($this->coachA->notifications()->first()->read_at);
    }

    public function test_roles_without_permission_cannot_open_composer(): void
    {
        $this->actingAs($this->coachA)
            ->get(route('admin.notifications.create'))
            ->assertForbidden();

        $this->actingAs($this->coachA)
            ->post(route('admin.notifications.store'), [
                'type' => 'operational',
                'coach_ids' => [$this->coachB->id],
                'title' => 'X',
                'message' => 'Y',
            ])
            ->assertForbidden();
    }
}
