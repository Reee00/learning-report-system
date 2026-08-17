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

    public function index()
    {
        $this->ensurePermission('programs.view');

        $programs = Program::with('programClasses.schoolClass.school')
            ->latest()
            ->paginate(15);
        $classes = SchoolClass::with('school')
            ->orderBy('name')
            ->get();

        return view('admin.master.programs', compact('programs', 'classes'));
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
