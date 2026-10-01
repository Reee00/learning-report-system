<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachClass;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CoachController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActivityLogService $activityLog,
    ) {
    }

    public function index(Request $request)
    {
        $this->ensurePermission('coaches.view');

        $query = User::where('role', User::ROLE_COACH)
            ->with(['coachClasses.schoolClass.school']);

        // Scope sekolah ditegakkan di QUERY, bukan di tampilan: PIC sekolah
        // tidak pernah menerima baris coach dari sekolah lain, sehingga
        // manipulasi URL/query pun tidak bisa membocorkannya.
        $this->scopeCoachQueryToSchools($query, $this->viewerSchoolScope());

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $coaches = $query->paginate(15)->withQueryString();

        $this->hideContactWhenNotPermitted($coaches->getCollection());

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
        $this->ensureCoachInScope($coach);

        $coach->load('coachClasses.schoolClass.school');

        // Dihitung SEBELUM relasi disaring, supaya daftar kelas yang sudah
        // di-assign tetap lengkap saat dipakai untuk menyembunyikan pilihan.
        $assignedClassIds = $coach->coachClasses->pluck('class_id')->all();
        $schoolScope = $this->viewerSchoolScope();

        if ($schoolScope !== null) {
            // Viewer bersekolah-terbatas tidak perlu tahu penugasan coach ini
            // di sekolah lain — itu informasi sekolah lain, bukan sekolahnya.
            $coach->setRelation('coachClasses', $coach->coachClasses
                ->filter(fn (CoachClass $assignment): bool => in_array(
                    (int) $assignment->schoolClass?->school_id,
                    $schoolScope,
                    true
                ))
                ->values());
        }

        $this->hideContactWhenNotPermitted([$coach]);

        $availableQuery = SchoolClass::with('school')
            ->whereNotIn('id', $assignedClassIds)
            ->orderBy('name');

        if ($schoolScope !== null) {
            $availableQuery->whereIn('school_id', $schoolScope);
        }

        $availableClasses = $availableQuery->get()->groupBy('school.name');

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

        $this->activityLog->log(
            $request->user(),
            'coach.assigned',
            'coach_class',
            $assignment->id,
            "Coach {$coach->name} di-assign ke kelas {$class->name} ({$class->school->name})",
            ['coach_id' => $coach->id, 'class_id' => $class->id, 'school_id' => $class->school_id],
            $request,
        );

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

        $this->activityLog->log(
            request()->user(),
            'coach.unassigned',
            'coach_class',
            $assignment->id,
            "Assignment coach {$coach->name} ke kelas #{$assignment->class_id} dihapus",
            ['coach_id' => $coach->id, 'class_id' => $assignment->class_id],
        );

        $assignment->delete();

        return back()->with('success', 'Assignment Coach berhasil dihapus.');
    }

    private function ensureCoach(User $coach): void
    {
        abort_unless($coach->role === User::ROLE_COACH, 404, 'Coach tidak ditemukan.');
    }

    /**
     * Daftar sekolah yang membatasi pandangan viewer, atau NULL bila viewer
     * memang berpandangan global (SuperAdmin, Relation, SPV Coach).
     *
     * Memakai AuthorizationService supaya batas sekolah di sini tidak pernah
     * berbeda dengan batas yang dipakai modul lain. Array KOSONG berarti PIC
     * belum diplot ke sekolah mana pun — dan itu berarti tidak melihat coach
     * mana pun, bukan berarti melihat semuanya.
     *
     * @return array<int, int>|null
     */
    private function viewerSchoolScope(): ?array
    {
        $user = request()->user();

        if (! $user instanceof User) {
            return [];
        }

        $schoolIds = $this->authorization->accessibleSchoolIds($user);

        return $schoolIds === null
            ? null
            : array_values(array_map('intval', $schoolIds));
    }

    /**
     * Batasi query coach ke sekolah yang menjadi wewenang viewer.
     *
     * Seorang coach dianggap berada dalam scope bila ia menyentuh sekolah itu
     * lewat salah satu dari tiga jalur penugasan yang ada di sistem — bukan
     * hanya `coach_classes`, karena coach bisa mengajar lewat sesi terjadwal
     * tanpa penugasan permanen. Semua jalur diperiksa, jadi coach tidak pernah
     * "hilang" dari PIC sekolahnya sendiri.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  array<int, int>|null  $schoolIds
     */
    private function scopeCoachQueryToSchools($query, ?array $schoolIds): void
    {
        if ($schoolIds === null) {
            return;
        }

        if ($schoolIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($scoped) use ($schoolIds): void {
            $scoped
                ->whereHas('coachClasses.schoolClass', function ($class) use ($schoolIds): void {
                    $class->whereIn('school_id', $schoolIds);
                })
                ->orWhereHas('teachingSchedules', function ($session) use ($schoolIds): void {
                    $session->whereIn('school_id', $schoolIds);
                })
                ->orWhereHas('additionalSchedules', function ($session) use ($schoolIds): void {
                    $session->whereIn('school_id', $schoolIds);
                });
        });
    }

    /**
     * URL detail coach harus mengikuti scope yang sama dengan daftarnya —
     * menebak id coach sekolah lain harus berakhir 403, bukan halaman terbuka.
     */
    private function ensureCoachInScope(User $coach): void
    {
        $schoolIds = $this->viewerSchoolScope();

        if ($schoolIds === null) {
            return;
        }

        $query = User::query()->whereKey($coach->getKey());
        $this->scopeCoachQueryToSchools($query, $schoolIds);

        abort_unless(
            $query->exists(),
            403,
            'Coach berada di luar sekolah yang menjadi wewenang Anda.'
        );
    }

    /**
     * Nomor WhatsApp coach adalah data kontak pribadi: hanya role dengan izin
     * `coaches.contact` yang menerimanya.
     *
     * Nilainya dibuang dari model di sisi SERVER, bukan sekadar disembunyikan
     * di Blade — sehingga tampilan, JSON, maupun potongan kode lain tidak
     * pernah bisa membacanya.
     *
     * @param  iterable<int, User>  $coaches
     */
    private function hideContactWhenNotPermitted(iterable $coaches): void
    {
        $user = request()->user();

        if ($user instanceof User && $this->authorization->allows($user, 'coaches.contact')) {
            return;
        }

        foreach ($coaches as $coach) {
            $coach->setAttribute('whatsapp', null);
            $coach->makeHidden('whatsapp');
        }
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
