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

    public function index()
    {
        $this->ensurePermission('program_classes.view');

        $classes = SchoolClass::with('school')->paginate(20);
        $schools = School::orderBy('name')->get();
        return view('admin.master.classes', compact('classes', 'schools'));
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
