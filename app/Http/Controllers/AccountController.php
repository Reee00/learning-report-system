<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Account Settings (review meeting LRS 2026-10-01).
 *
 * Terbuka untuk SELURUH role — tidak ada permission gate di sini, karena
 * setiap user hanya boleh mengubah AKUNNYA SENDIRI dan tidak ada wewenang
 * administratif yang terlibat. Otorisasinya melekat pada `$request->user()`:
 * id user tidak pernah diambil dari parameter URL, body, atau query, sehingga
 * menambahkan id user lain pada request tidak mengubah siapa yang disunting.
 *
 * Nomor WhatsApp adalah data KONTAK PRIBADI. Halaman ini hanya menampilkan
 * nomor milik user yang login; nomor user lain tidak pernah dirender di sini
 * (daftar coach punya halaman sendiri dengan izin `coaches.contact`).
 */
class AccountController extends Controller
{
    public function __construct(private ActivityLogService $activityLog)
    {
    }

    public function edit(Request $request)
    {
        return view('account.settings', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Profil: Nama + Nomor WhatsApp.
     *
     * Email sengaja tidak ikut: ia identifier login, dan mengubahnya dari sini
     * akan menabrak aturan `unique:users,email` tanpa jalur verifikasi apa pun.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        // Normalisasi SEBELUM validasi supaya `+62 812-3456-7890`, `62812…`,
        // dan `0812…` sama-sama lolos sebagai bentuk yang sama — dan yang
        // tersimpan selalu bentuk kanonik `08xxxxxxxxxx`.
        $request->merge([
            'whatsapp' => User::normalizeWhatsapp($request->input('whatsapp')),
        ]);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'whatsapp' => User::whatsappValidationRules(),
        ], [
            'name.required'  => 'Nama wajib diisi.',
            'name.max'       => 'Nama maksimal 100 karakter.',
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx (10–15 digit).',
        ]);

        $user->update([
            'name'     => $validated['name'],
            'whatsapp' => $validated['whatsapp'] ?: null,
        ]);

        return back()->with('success', 'Pengaturan akun berhasil disimpan.');
    }

    /**
     * Password: wajib menyertakan password SAAT INI.
     *
     * Alasan meminta password lama: perubahan password adalah aksi sensitif
     * yang mengubah cara masuk ke akun. Tanpa konfirmasi ini, sesi yang
     * dibiarkan terbuka di perangkat bersama cukup untuk mengunci pemilik asli
     * di luar akunnya sendiri. Aturan `current_password` bawaan Laravel dipakai
     * supaya pemeriksaannya memakai hashing yang sama dengan login — tidak ada
     * perbandingan password buatan sendiri di sini.
     *
     * Password BARU memakai aturan yang sudah berlaku di aplikasi
     * (`Admin\UserController`: minimal 6 karakter + konfirmasi) dan disimpan
     * dengan `Hash::make`, mekanisme hashing yang sama dengan pembuatan akun.
     *
     * Activity log mencatat PERISTIWANYA saja — password lama, password baru,
     * dan konfirmasinya tidak pernah ikut dicatat.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        // Error bag terpisah ('password') supaya pesan dari form password tidak
        // tercampur dengan pesan form profil di kartu sebelahnya.
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required'       => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak cocok.',
            'password.required'               => 'Password baru wajib diisi.',
            'password.min'                    => 'Password baru minimal 6 karakter.',
            'password.confirmed'              => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Metadata sengaja kosong: tidak ada satu pun nilai password yang
        // dikirim ke activity log. ActivityLogService tetap menyaring kunci
        // sensitif sebagai lapis kedua bila kelak metadata ditambahkan.
        $this->activityLog->log(
            $user,
            'user.password_changed',
            'user',
            $user->id,
            'Password diubah sendiri oleh '.$user->name,
            request: $request,
        );

        return back()->with('success', 'Password berhasil diubah.');
    }
}
