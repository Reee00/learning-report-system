<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachClass;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CoachController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function index(Request $request)
    {
        $this->ensurePermission('coaches.view');

        $query = User::where('role', User::ROLE_COACH)
            ->with(['coachClasses.schoolClass.school']);

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $coaches = $query->paginate(15)->withQueryString();

        return view('admin.master.coaches', compact('coaches', 'search'));
    }

    public function store(Request $request)
    {
        $this->ensurePermission('coaches.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_COACH,
            'school_id' => null,
        ]);

        return back()->with('success', 'Coach berhasil ditambahkan.');
    }

    public function show(User $coach)
    {
        $this->ensurePermission('coaches.view');
        $this->ensureCoach($coach);

        $coach->load('coachClasses.schoolClass.school');

        $assignedClassIds = $coach->coachClasses->pluck('class_id')->all();
        $availableClasses = SchoolClass::with('school')
            ->whereNotIn('id', $assignedClassIds)
            ->orderBy('name')
            ->get()
            ->groupBy('school.name');

        return view('admin.master.coach_show', compact('coach', 'availableClasses'));
    }

    public function update(Request $request, User $coach)
    {
        $this->ensurePermission('coaches.update');
        $this->ensureCoach($coach);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($coach->id),
            ],
        ]);

        $coach->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return back()->with('success', 'Data Coach berhasil diperbarui.');
    }

    public function assign(Request $request, User $coach)
    {
        $this->ensurePermission('coaches.assign');
        $this->ensureCoach($coach);

        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
        ]);

        $class = SchoolClass::with('school')->findOrFail($validated['class_id']);
        abort_unless($class->school !== null, 422, 'Program Kelas belum terhubung ke sekolah.');

        $assignment = CoachClass::firstOrCreate([
            'coach_id' => $coach->id,
            'class_id' => $class->id,
        ]);

        if (!$assignment->wasRecentlyCreated) {
            return back()->with('error', 'Coach sudah di-assign ke kelas ini.');
        }

        return back()->with('success', 'Kelas berhasil di-assign ke Coach.');
    }

    public function unassign(User $coach, CoachClass $assignment)
    {
        $this->ensurePermission('coaches.reassign');
        $this->ensureCoach($coach);

        abort_unless(
            $assignment->coach_id === $coach->id,
            403,
            'Assignment tidak dimiliki Coach ini.'
        );

        $assignment->delete();

        return back()->with('success', 'Assignment Coach berhasil dihapus.');
    }

    private function ensureCoach(User $coach): void
    {
        abort_unless($coach->role === User::ROLE_COACH, 404, 'Coach tidak ditemukan.');
    }

    private function ensurePermission(string $permission): void
    {
        $user = request()->user();

        abort_unless(
            $user instanceof User && $this->authorization->allows($user, $permission),
            403,
            'Permission tidak mencukupi.'
        );
    }
}
