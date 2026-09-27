<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Relation assign coach (audit UX 2026-09-11, P1).
 *
 * Relation re-uses the existing SPV Coach workflow (CoachController +
 * CoachClass) — no parallel assignment system. Verifies:
 * - Relation can assign and unassign a coach to/from a class.
 * - Coach (the assigned role itself) cannot assign.
 * - Assignment lands in the same coach_classes table the rest of the app reads.
 */
class RelationCoachAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private SchoolClass $classA;
    private User $coach;
    private User $relation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School A']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A']);

        $this->coach = User::create([
            'name' => 'Coach', 'email' => 'coach@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        $this->relation = User::create([
            'name' => 'Relation', 'email' => 'relation@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);
    }

    public function test_relation_can_assign_coach_to_class(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.coaches.assign', $this->coach), [
                'class_id' => $this->classA->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coach_classes', [
            'coach_id' => $this->coach->id,
            'class_id' => $this->classA->id,
        ]);

        // Assignment is logged.
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'coach.assigned',
            'subject_type' => 'coach_class',
        ]);
    }

    public function test_relation_can_unassign_coach(): void
    {
        $assignment = CoachClass::create([
            'coach_id' => $this->coach->id,
            'class_id' => $this->classA->id,
        ]);

        $this->actingAs($this->relation)
            ->delete(route('admin.coaches.unassign', [$this->coach, $assignment]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('coach_classes', [
            'id' => $assignment->id,
        ]);
    }

    public function test_coach_cannot_assign(): void
    {
        $this->actingAs($this->coach)
            ->post(route('admin.coaches.assign', $this->coach), [
                'class_id' => $this->classA->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('coach_classes', [
            'coach_id' => $this->coach->id,
        ]);
    }
}
