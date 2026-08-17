<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Daftar semua akun
    public function index(Request $request)
    {
        $query = User::with(['school', 'schools'])->latest();

        // Filter berdasarkan role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter berdasarkan nama/email
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users   = $query->paginate(15)->withQueryString();
        $schools = School::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'schools'));
    }

    // Simpan akun baru
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6|confirmed',
            'role'      => ['required', Rule::in(User::roleKeys())],
            'school_id' => 'nullable|exists:schools,id',
            'school_ids' => 'nullable|array',
            'school_ids.*' => 'integer|distinct|exists:schools,id',
        ]);

        $schoolIds = $this->schoolIdsFromRequest($request);
        $isSchoolScoped = in_array($request->role, User::schoolScopedRoles(), true);

        if ($isSchoolScoped && $schoolIds === []) {
            return back()
                ->withErrors(['school_ids' => 'Minimal satu sekolah wajib dipilih untuk role ini.'])
                ->withInput();
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'school_id' => $isSchoolScoped ? ($schoolIds[0] ?? null) : null,
        ]);

        $user->schools()->sync($isSchoolScoped ? $schoolIds : []);

        return back()->with('success', 'Akun berhasil dibuat!');
    }

    // Update akun (nama, email, role)
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role'      => ['required', Rule::in(User::roleKeys())],
            'school_id' => 'nullable|exists:schools,id',
            'school_ids' => 'nullable|array',
            'school_ids.*' => 'integer|distinct|exists:schools,id',
        ]);

        $schoolIds = $this->schoolIdsFromRequest($request);
        $isSchoolScoped = in_array($request->role, User::schoolScopedRoles(), true);

        if ($isSchoolScoped && $schoolIds === []) {
            return back()
                ->withErrors(['school_ids' => 'Minimal satu sekolah wajib dipilih untuk role ini.'])
                ->withInput();
        }

        $user->update([
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'school_id' => $isSchoolScoped ? ($schoolIds[0] ?? null) : null,
        ]);

        $user->schools()->sync($isSchoolScoped ? $schoolIds : []);

        return back()->with('success', 'Akun berhasil diperbarui!');
    }

    // Reset password
    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', "Password akun {$user->name} berhasil direset!");
    }

    // Hapus akun
    public function destroy(User $user)
    {
        // Cegah user yang sedang login menghapus akunnya sendiri
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri!');
        }

        // reports.coach_id memakai FK RESTRICT: laporan historis tidak boleh
        // hilang, jadi penolakan dilakukan dengan pesan, bukan error database.
        if ($user->reports()->exists()) {
            return back()->with(
                'error',
                "Akun {$user->name} tidak bisa dihapus karena masih memiliki laporan. Nonaktifkan penugasan kelasnya sebagai gantinya."
            );
        }

        $user->delete();
        return back()->with('success', 'Akun berhasil dihapus.');
    }

    private function schoolIdsFromRequest(Request $request): array
    {
        $schoolIds = $request->input('school_ids', []);

        if (!is_array($schoolIds)) {
            $schoolIds = [];
        }

        if ($schoolIds === [] && $request->filled('school_id')) {
            $schoolIds = [$request->input('school_id')];
        }

        return array_values(array_unique(array_map('intval', $schoolIds)));
    }
}
