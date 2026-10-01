<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private ActivityLogService $activityLog)
    {
    }
    /**
     * Hardening login (audit keamanan 2026-09-11):
     * - Rate limit by IP + email (progressive decay default Laravel).
     * - Pesan error generik — tidak membocorkan apakah email terdaftar.
     * - Session regenerate setelah login sukses (anti session fixation).
     */

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

        // Throttle per IP + akun: 5 percobaan gagal per kombinasi per menit.
        // Dua kunci terpisah — IP yang menyasar banyak akun dan akun yang
        // diserang dari banyak IP keduanya ter-throttle. RateLimiter::hit
        // memakai decay progresif (semakin sering gagal, makin lama).
        $throttleKey = strtolower($credentials['email']) . '|' . $request->ip();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ])],
            ]);
        }

        // Coba login dengan credentials yang diberikan
        if (Auth::attempt($credentials)) {
            \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);
            // Keamanan: regenerate session ID (anti session fixation).
            $request->session()->regenerate();

            $this->activityLog->log(
                Auth::user(),
                'auth.login',
                'user',
                Auth::id(),
                'Login berhasil: ' . Auth::user()->name,
                request: $request,
            );

            return $this->redirectByRole(Auth::user()->role);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey);

        $this->activityLog->log(
            null,
            'auth.login_failed',
            null,
            null,
            'Percobaan login gagal untuk ' . $credentials['email'],
            request: $request,
        );

        // Pesan generik yang sama untuk email salah / password salah —
        // mencegah account enumeration.
        return back()
            ->withErrors(['email' => 'Email atau password salah.'])
            ->onlyInput('email');
    }

    // Redirect berdasarkan role
    private function redirectByRole(string $role)
    {
        // Pemetaan role -> halaman awal tinggal di User::homeRouteName() supaya
        // handler sesi kedaluwarsa memakai tabel yang sama, bukan menebak.
        //
        // Fallback ke '/' dilarang: '/' me-redirect ke login, sehingga role tanpa
        // mapping akan terjebak redirect loop login <-> '/'.
        $name = User::homeRouteName($role);

        if ($name === null) {
            abort(403, 'Role akun belum memiliki halaman awal. Hubungi SuperAdmin.');
        }

        return redirect()->route($name);
    }

    // Logout
    public function logout(Request $request)
    {
        $this->activityLog->log(
            $request->user(),
            'auth.logout',
            'user',
            $request->user()?->id,
            'Logout: ' . $request->user()?->name,
            request: $request,
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
