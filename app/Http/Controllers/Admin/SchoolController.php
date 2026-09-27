<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActivityLogService $activityLog,
    ) {
    }

    public function index(Request $request)
    {
        $this->ensurePermission('schools.view');

        $query = School::withCount('classes');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $schools = $query->paginate(15)->withQueryString();
        $programs = Program::where('status', 'active')->orderBy('name')->get();
        
        return view('admin.master.schools', compact('schools', 'programs', 'search'));
    }

    public function store(Request $request)
    {
        $this->ensurePermission('schools.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'class_names' => ['nullable', 'string'], // comma or newline separated
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $school = School::create([
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'pic_name' => $validated['pic_name'] ?? null,
            ]);

            if (!empty($validated['class_names'])) {
                $classNames = array_filter(array_map('trim', preg_split('/[\n,]+/', $validated['class_names'])));
                foreach ($classNames as $className) {
                    if (empty($className)) continue;

                    $class = $school->classes()->create([
                        'name' => $className
                    ]);

                    if (!empty($validated['program_ids'])) {
                        $class->programs()->sync($validated['program_ids']);
                    }
                }
            }

            $this->activityLog->log(
                $request->user(),
                'school.created',
                'school',
                $school->id,
                "Sekolah dibuat: {$school->name}",
                ['address' => $school->address],
                $request,
            );
        });

        return back()->with('success', 'Sekolah berhasil ditambahkan beserta kelas dan programnya.');
    }

    public function update(Request $request, School $school)
    {
        $this->ensurePermission('schools.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'pic_name' => ['nullable', 'string', 'max:100'],
        ]);

        $school->update($validated);

        $this->activityLog->log(
            $request->user(),
            'school.updated',
            'school',
            $school->id,
            "Data sekolah diperbarui: {$school->name}",
            request: $request,
        );

        return back()->with('success', 'Data sekolah berhasil diperbarui.');
    }

    public function destroy(Request $request, School $school)
    {
        $this->ensurePermission('schools.delete');

        // reports.school_id memakai FK RESTRICT: laporan historis tidak boleh
        // hilang, jadi penolakan dilakukan dengan pesan, bukan error database.
        if ($school->reports()->exists()) {
            return back()->with(
                'error',
                'Sekolah tidak bisa dihapus karena masih memiliki laporan. Hapus atau pindahkan laporan terkait terlebih dahulu.'
            );
        }

        $this->activityLog->log(
            $request->user(),
            'school.deleted',
            'school',
            $school->id,
            "Sekolah dihapus: {$school->name}",
            request: $request,
        );

        $school->delete();
        return redirect()->route('admin.schools.index')->with('success', 'Sekolah berhasil dihapus.');
    }

    public function show(School $school)
    {
        $this->ensurePermission('schools.view');

        // School Workspace (2026-09-24): satu halaman untuk seluruh setup
        // sekolah — informasi, kelas, dan program per kelas. Tidak ada
        // master-data baru: kelas tetap App\Models\SchoolClass dan program
        // tetap App\Models\Program lewat pivot program_classes yang sama.
        $classes = $school->classes()
            ->with(['programs', 'coachAssignments.coach'])
            ->withCount(['students', 'reports'])
            ->orderBy('name')
            ->get();

        $programs = Program::where('status', 'active')->orderBy('name')->get();

        $user = request()->user();
        $isSuperOrRelation = $user instanceof User
            && ($user->isSuperAdmin() || $user->isRelationUser());

        $canManageClasses = $user instanceof User
            && $this->authorization->allows($user, 'program_classes.update');

        // Kelas master dari sekolah lain = kandidat "assign kelas yang sudah
        // ada". Hanya kelas yang masih kosong (tanpa murid/laporan/jadwal)
        // yang boleh dipindah, agar school_id pada laporan & jadwal historis
        // tidak pernah bertentangan dengan school_id kelasnya.
        $movableClasses = $canManageClasses
            ? SchoolClass::with(['school', 'programs'])
                ->withCount(['students', 'reports', 'teachingSchedules'])
                ->where('school_id', '!=', $school->id)
                ->orderBy('name')
                ->get()
                ->filter(fn (SchoolClass $class): bool => (int) $class->students_count === 0
                    && (int) $class->reports_count === 0
                    && (int) $class->teaching_schedules_count === 0)
                ->filter(fn (SchoolClass $class): bool => $this->authorization->canAccessSchool(
                    $user,
                    (int) $class->school_id
                ))
                ->values()
            : collect();

        return view('admin.master.school_show', compact(
            'school',
            'classes',
            'programs',
            'movableClasses',
            'isSuperOrRelation',
            'canManageClasses',
        ));
    }

    /**
     * Assign kelas ke sekolah ini. Dua mode:
     * - `new`      : buat kelas baru di sekolah ini (nama wajib unik di
     *                sekolah ini — mencegah duplikat).
     * - `existing` : pindahkan kelas master yang sudah ada dari sekolah lain
     *                (hanya kelas kosong; lihat show()).
     *
     * Program dari Master Program dapat langsung dipilih saat assign.
     */
    public function storeClass(Request $request, School $school)
    {
        $this->ensurePermission('program_classes.create');
        $this->assertSchoolInScope($school);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['new', 'existing'])],
            'name' => ['nullable', 'required_if:mode,new', 'string', 'max:100'],
            'class_id' => ['nullable', 'required_if:mode,existing', 'integer', 'exists:classes,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
        ], [], [
            'name' => 'nama kelas',
            'class_id' => 'kelas',
        ]);

        $programIds = array_values(array_unique(array_map('intval', $validated['program_ids'] ?? [])));

        $class = DB::transaction(function () use ($validated, $school, $programIds, $request): SchoolClass {
            if ($validated['mode'] === 'existing') {
                $class = SchoolClass::findOrFail($validated['class_id']);

                if ((int) $class->school_id === (int) $school->id) {
                    throw $this->validationException([
                        'class_id' => "Kelas {$class->name} sudah terdaftar di sekolah ini.",
                    ]);
                }

                // Cakupan sekolah asal maupun tujuan harus boleh diakses.
                $this->assertSchoolInScope($class->school);
                $this->assertClassIsMovable($class);

                $class->update(['school_id' => $school->id]);
            } else {
                $name = trim((string) $validated['name']);

                if ($this->classNameExists($school, $name)) {
                    throw $this->validationException([
                        'name' => "Kelas \"{$name}\" sudah ada di sekolah ini. Pilih kelas tersebut pada daftar kelas yang sudah terdaftar.",
                    ]);
                }

                $class = $school->classes()->create(['name' => $name]);
            }

            if ($programIds !== []) {
                // sync() pada pivot ber-unique(program_id, class_id): program
                // yang sudah terpasang tidak pernah terduplikasi.
                $class->programs()->syncWithoutDetaching($programIds);
            }

            $this->activityLog->log(
                $request->user(),
                $validated['mode'] === 'existing' ? 'class.assigned' : 'class.created',
                'school_class',
                $class->id,
                ($validated['mode'] === 'existing' ? 'Kelas dipindahkan ke ' : 'Kelas dibuat di ')
                    ."{$school->name}: {$class->name}",
                ['school_id' => $school->id, 'class_id' => $class->id],
                $request,
            );

            return $class;
        });

        return redirect()
            ->route('admin.schools.show', $school)
            ->with('success', "Kelas {$class->name} berhasil di-assign ke {$school->name}.");
    }

    /**
     * Perbarui kelas dalam workspace: ganti nama dan/atau susunan program.
     */
    public function updateClass(Request $request, School $school, SchoolClass $class)
    {
        $this->ensurePermission('program_classes.update');
        $this->assertSchoolInScope($school);
        $this->assertClassBelongsToSchool($school, $class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
        ]);

        $name = trim($validated['name']);

        if ($this->classNamedExists($school, $name, $class->id)) {
            throw $this->validationException([
                'name' => "Kelas \"{$name}\" sudah ada di sekolah ini.",
            ]);
        }

        $programIds = array_values(array_unique(array_map('intval', $validated['program_ids'] ?? [])));

        DB::transaction(function () use ($class, $name, $programIds, $request, $school): void {
            $class->update(['name' => $name]);
            // sync() pada pivot ber-unique(program_id, class_id).
            $class->programs()->sync($programIds);

            $this->activityLog->log(
                $request->user(),
                'class.updated',
                'school_class',
                $class->id,
                "Kelas diperbarui di {$school->name}: {$class->name}",
                ['school_id' => $school->id, 'class_id' => $class->id, 'program_ids' => $programIds],
                $request,
            );
        });

        return redirect()
            ->route('admin.schools.show', $school)
            ->with('success', "Kelas {$class->name} berhasil diperbarui.");
    }

    /**
     * Hapus kelas dari sekolah ini. Data historis tidak boleh hilang, jadi
     * kelas yang masih punya murid, laporan, atau jadwal ditolak dengan pesan
     * (bukan error foreign key / cascade diam-diam).
     */
    public function destroyClass(Request $request, School $school, SchoolClass $class)
    {
        $this->ensurePermission('program_classes.delete');
        $this->assertSchoolInScope($school);
        $this->assertClassBelongsToSchool($school, $class);

        $blockers = [];
        if ($class->reports()->exists()) {
            $blockers[] = 'laporan';
        }
        if ($class->students()->exists()) {
            $blockers[] = 'murid';
        }
        if ($class->teachingSchedules()->exists()) {
            $blockers[] = 'jadwal mengajar';
        }

        if ($blockers !== []) {
            return back()->with(
                'error',
                "Kelas {$class->name} tidak bisa dihapus karena masih memiliki "
                .implode(', ', $blockers).'. Pindahkan atau hapus data terkait terlebih dahulu.'
            );
        }

        $this->activityLog->log(
            $request->user(),
            'class.deleted',
            'school_class',
            $class->id,
            "Kelas dihapus dari {$school->name}: {$class->name}",
            ['school_id' => $school->id, 'class_id' => $class->id],
            $request,
        );

        $class->delete();

        return redirect()
            ->route('admin.schools.show', $school)
            ->with('success', "Kelas {$class->name} berhasil dihapus.");
    }

    private function classNameExists(School $school, string $name): bool
    {
        return $this->classNamedExists($school, $name);
    }

    /**
     * Perbandingan nama kelas tidak peka huruf besar/kecil dan mengabaikan
     * spasi tepi — "Grade 5A" dan "grade 5a " adalah kelas yang sama.
     */
    private function classNamedExists(School $school, string $name, ?int $ignoreId = null): bool
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function assertClassIsMovable(SchoolClass $class): void
    {
        $blockers = [];
        if ($class->students()->exists()) {
            $blockers[] = 'murid';
        }
        if ($class->reports()->exists()) {
            $blockers[] = 'laporan';
        }
        if ($class->teachingSchedules()->exists()) {
            $blockers[] = 'jadwal mengajar';
        }

        if ($blockers !== []) {
            throw $this->validationException([
                'class_id' => "Kelas {$class->name} sudah memiliki ".implode(', ', $blockers)
                    .' sehingga tidak bisa dipindahkan antar sekolah.',
            ]);
        }
    }

    private function assertClassBelongsToSchool(School $school, SchoolClass $class): void
    {
        abort_unless(
            (int) $class->school_id === (int) $school->id,
            404,
            'Kelas tidak terdaftar pada sekolah ini.'
        );
    }

    private function assertSchoolInScope(School $school): void
    {
        $user = request()->user();

        abort_unless(
            $user instanceof User && $this->authorization->canAccessSchool($user, (int) $school->id),
            403,
            'Sekolah ini di luar scope Anda.'
        );
    }

    private function validationException(array $errors): \Illuminate\Validation\ValidationException
    {
        return \Illuminate\Validation\ValidationException::withMessages($errors);
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
