<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
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

        DB::transaction(function () use ($validated) {
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
        return back()->with('success', 'Data sekolah berhasil diperbarui.');
    }

    public function destroy(School $school)
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

        $school->delete();
        return redirect()->route('admin.schools.index')->with('success', 'Sekolah berhasil dihapus.');
    }

    public function show(School $school)
    {
        $this->ensurePermission('schools.view');
        
        $school->load(['classes.programs', 'classes.students', 'classes.coachAssignments.coach']);
        
        // Extract unique programs from classes to show at school level
        $programs = $school->classes->flatMap->programs->unique('id')->values();

        return view('admin.master.school_show', compact('school', 'programs'));
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
