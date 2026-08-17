<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // Tampilkan form login
    public function showForm()
    {
        // Jika sudah login, langsung redirect ke dashboard sesuai role
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user()->role);
        }
        return view('auth.login');
    }

    // Proses login
    public function login(Request $request)
    {
        // Validasi input
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Coba login dengan credentials yang diberikan
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate(); // Keamanan: regenerate session ID
            return $this->redirectByRole(Auth::user()->role);
        }

        // Jika gagal login, kembali ke form dengan pesan error
        return back()
            ->withErrors(['email' => 'Email atau password salah.'])
            ->onlyInput('email');
    }

    // Redirect berdasarkan role
    private function redirectByRole(string $role)
    {
        // Setiap role harus punya landing page yang benar-benar bisa diakses.
        // Fallback ke '/' dilarang: '/' me-redirect ke login, sehingga role tanpa
        // mapping akan terjebak redirect loop login <-> '/'.
        return match ($role) {
            User::ROLE_SUPERADMIN => redirect()->route('admin.dashboard'),
            User::ROLE_RELATION   => redirect()->route('admin.schools.index'),
            User::ROLE_SPV_COACH  => redirect()->route('admin.coaches.index'),
            User::ROLE_COACH      => redirect()->route('coach.reports.index'),
            User::ROLE_SCHOOL_PIC     => redirect()->route('pic.dashboard'),
            User::ROLE_TEACHER_SCHOOL => redirect()->route('attendance.index'),
            User::ROLE_FINANCE        => redirect()->route('attendance.index'),
            default               => abort(403, 'Role akun belum memiliki halaman awal. Hubungi SuperAdmin.'),
        };
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
