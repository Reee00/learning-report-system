<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function index(Request $request)
    {
        $this->ensurePermission('program_classes.view');

        $query = SchoolClass::with('school');

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

        SchoolClass::create($validated);
        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->ensurePermission('program_classes.update');

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $class->update($validated);
        return back()->with('success', 'Data kelas berhasil diperbarui.');
    }

    public function destroy(SchoolClass $class)
    {
        $this->ensurePermission('program_classes.delete');

        // reports.class_id memakai FK RESTRICT: laporan historis tidak boleh
        // hilang, jadi penolakan dilakukan dengan pesan, bukan error database.
        if ($class->reports()->exists()) {
            return back()->with(
                'error',
                'Kelas tidak bisa dihapus karena masih memiliki laporan. Hapus atau pindahkan laporan terkait terlebih dahulu.'
            );
        }

        $class->delete();
        return back()->with('success', 'Kelas berhasil dihapus.');
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
