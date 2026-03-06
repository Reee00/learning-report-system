<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        return match ($role) {
            'admin'      => redirect()->route('admin.dashboard'),
            'coach'      => redirect()->route('coach.reports.index'),
            'school_pic' => redirect()->route('pic.dashboard'),
            default      => redirect('/'),
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