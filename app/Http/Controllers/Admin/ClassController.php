<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActivityLogService $activityLog,
    ) {
    }

    public function index(Request $request)
    {
        $this->ensurePermission('program_classes.view');

        // Master Class adalah sumber data kelas yang dipakai ulang oleh
        // School Workspace dan jadwal. Kolom "digunakan di" dihitung dari
        // relasi yang sudah ada (programs/students/reports/schedules) —
        // tidak ada tabel atau sistem kelas kedua.
        $query = SchoolClass::with(['school', 'programs'])
            ->withCount(['students', 'reports', 'teachingSchedules']);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('school', function ($q) use ($search) {
                      $q->where('name', 'like', '%' . $search . '%');
                  });
        }

        $classes = $query->paginate(20)->withQueryString();
        $schools = School::orderBy('name')->get();
        return view('admin.master.classes', compact('classes', 'schools', 'search'));
    }

    public function store(Request $request)
    {
        $this->ensurePermission('program_classes.create');

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $this->assertSchoolInScope((int) $validated['school_id']);

        $name = trim($validated['name']);

        // Cegah duplikat kelas pada sekolah yang sama (perbandingan tidak
        // peka huruf besar/kecil). Database tidak memasang unique index
        // karena data lama bisa saja sudah duplikat — lihat catatan migrasi.
        if ($this->classNamedExists((int) $validated['school_id'], $name)) {
            return back()
                ->withInput()
                ->withErrors(['name' => "Kelas \"{$name}\" sudah ada di sekolah ini."]);
        }

        $class = SchoolClass::create([
            'school_id' => (int) $validated['school_id'],
            'name' => $name,
        ]);

        $this->activityLog->log(
            $request->user(),
            'class.created',
            'school_class',
            $class->id,
            "Kelas dibuat: {$class->name} (sekolah #{$class->school_id})",
            request: $request,
        );

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->ensurePermission('program_classes.update');

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $this->assertSchoolInScope((int) $validated['school_id']);

        $name = trim($validated['name']);

        if ($this->classNamedExists((int) $validated['school_id'], $name, $class->id)) {
            return back()
                ->withInput()
                ->withErrors(['name' => "Kelas \"{$name}\" sudah ada di sekolah ini."]);
        }

        $class->update([
            'school_id' => (int) $validated['school_id'],
            'name' => $name,
        ]);

        $this->activityLog->log(
            $request->user(),
            'class.updated',
            'school_class',
            $class->id,
            "Data kelas diperbarui: {$class->name} (sekolah #{$class->school_id})",
            request: $request,
        );

        return back()->with('success', 'Data kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, SchoolClass $class)
    {
        $this->ensurePermission('program_classes.delete');

        $this->assertSchoolInScope((int) $class->school_id);

        // reports.class_id memakai FK RESTRICT: laporan historis tidak boleh
        // hilang, jadi penolakan dilakukan dengan pesan, bukan error database.
        // students & teaching_schedules memakai CASCADE — menghapus kelas
        // akan menghapus keduanya diam-diam, sehingga juga ditolak di sini.
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
            "Kelas dihapus: {$class->name} (sekolah #{$class->school_id})",
            request: $request,
        );

        $class->delete();
        return back()->with('success', 'Kelas berhasil dihapus.');
    }

    /**
     * Nama kelas unik per sekolah, tidak peka huruf besar/kecil dan
     * mengabaikan spasi tepi.
     */
    private function classNamedExists(int $schoolId, string $name, ?int $ignoreId = null): bool
    {
        return SchoolClass::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function assertSchoolInScope(int $schoolId): void
    {
        $user = request()->user();

        abort_unless(
            $user instanceof User && $this->authorization->canAccessSchool($user, $schoolId),
            403,
            'Sekolah ini di luar scope Anda.'
        );
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
