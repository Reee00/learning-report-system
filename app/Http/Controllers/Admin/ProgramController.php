<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function index(Request $request)
    {
        $this->ensurePermission('programs.view');

        $query = Program::with('programClasses.schoolClass.school')->latest();

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        $programs = $query->paginate(15)->withQueryString();
        $classes = SchoolClass::with('school')
            ->orderBy('name')
            ->get();

        return view('admin.master.programs', compact('programs', 'classes', 'search'));
    }

    public function store(Request $request)
    {
        $this->ensurePermission('programs.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:programs,code'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', 'distinct', 'exists:classes,id'],
        ]);

        DB::transaction(function () use ($validated): void {
            $program = Program::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
            ]);

            $program->classes()->sync($validated['class_ids']);
        });

        return back()->with('success', 'Program berhasil ditambahkan.');
    }

    public function show(Program $program)
    {
        $this->ensurePermission('programs.view');
        $program->load('programClasses.schoolClass.school');
        return view('admin.master.program_show', compact('program'));
    }

    public function update(Request $request, Program $program)
    {
        $this->ensurePermission('programs.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:programs,code,' . $program->id],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', 'distinct', 'exists:classes,id'],
        ]);

        DB::transaction(function () use ($program, $validated): void {
            $program->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
            ]);

            $program->classes()->sync($validated['class_ids']);
        });

        return back()->with('success', 'Program berhasil diperbarui.');
    }

    public function destroy(Program $program)
    {
        $this->ensurePermission('programs.delete');

        // Allow deletion if not used in historical data (no specific requirement given, but it uses cascade/restrict).
        // Let's assume if it is linked to any classes, we can still delete it, or we refuse.
        // Wait, "delete tidak merusak data yang masih digunakan; jika sebuah Class/Program masih digunakan oleh School, gunakan behavior yang aman".
        if ($program->programClasses()->exists()) {
            return back()->with('error', 'Program tidak bisa dihapus karena masih digunakan oleh kelas/sekolah. Lepaskan dari kelas terlebih dahulu.');
        }

        $program->delete();
        return redirect()->route('admin.programs.index')->with('success', 'Program berhasil dihapus.');
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
