<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function index()
    {
        $this->ensurePermission('schools.view');

        $schools = School::withCount('classes')->paginate(15);
        return view('admin.master.schools', compact('schools'));
    }

    public function store(Request $request)
    {
        $this->ensurePermission('schools.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'pic_name' => ['nullable', 'string', 'max:100'],
        ]);

        School::create($validated);
        return back()->with('success', 'Sekolah berhasil ditambahkan.');
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
        return back()->with('success', 'Sekolah berhasil dihapus.');
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
