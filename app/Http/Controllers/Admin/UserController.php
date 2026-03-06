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
        $query = User::with('school')->latest();

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
            'role'      => 'required|in:admin,coach,school_pic',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        // school_id wajib diisi jika role = school_pic
        if ($request->role === 'school_pic' && !$request->filled('school_id')) {
            return back()
                ->withErrors(['school_id' => 'Sekolah wajib dipilih untuk role School PIC.'])
                ->withInput();
        }

        User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'school_id' => $request->role === 'school_pic' ? $request->school_id : null,
        ]);

        return back()->with('success', 'Akun berhasil dibuat!');
    }

    // Update akun (nama, email, role)
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role'      => 'required|in:admin,coach,school_pic',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        if ($request->role === 'school_pic' && !$request->filled('school_id')) {
            return back()
                ->withErrors(['school_id' => 'Sekolah wajib dipilih untuk role School PIC.'])
                ->withInput();
        }

        $user->update([
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'school_id' => $request->role === 'school_pic' ? $request->school_id : null,
        ]);

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
        // Cegah admin menghapus akunnya sendiri
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri!');
        }

        $user->delete();
        return back()->with('success', 'Akun berhasil dihapus.');
    }
}