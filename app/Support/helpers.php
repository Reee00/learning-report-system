<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('ctx_route')) {
    /**
     * Bangun URL route SAMBIL MEMBAWA konteks filter yang sedang aktif.
     *
     * MASALAH YANG DISELESAIKAN
     *
     * Halaman daftar (coach, sekolah, kelas, program, activity log, laporan,
     * kehadiran) punya filter GET. Tautan menuju halaman detail sebelumnya
     * ditulis `route('admin.coaches.show', $coach)`, sehingga seluruh isi
     * filter hilang begitu user masuk ke detail. Akibatnya:
     *
     *   - tombol/remah "kembali ke daftar" mendarat di daftar TANPA filter;
     *   - user harus mengetik ulang kata kuncinya;
     *   - pada rantai kehadiran (sekolah -> kelas -> sesi) filter tanggal
     *     hilang di SETIAP lompatan.
     *
     * Tombol Back bawaan browser memang memulihkan URL dari riwayat, tetapi
     * tautan apa pun yang menuju daftar — remah navigasi, tombol "Kembali",
     * tautan setelah simpan — tidak. Helper ini yang menjembataninya.
     *
     * PEMAKAIAN
     *
     *     <a href="{{ ctx_route('admin.coaches.show', $coach) }}">Detail</a>
     *     <x-breadcrumb :items="[['label' => 'Coach', 'url' => ctx_route('admin.coaches.index', [], true)]]" />
     *
     * ATURAN
     *
     * - `page` TIDAK dibawa secara default: nomor halaman daftar asal tidak
     *   ada artinya untuk halaman tujuan yang berbeda. Kirim `$keepPage = true`
     *   HANYA untuk tautan yang benar-benar kembali ke daftar asal.
     * - Parameter yang sudah menjadi bagian dari path route (mis. `{school}`,
     *   `{class}`) tidak pernah ditimpa oleh query string, supaya filter tidak
     *   bisa membelokkan tujuan tautan.
     * - Route yang tidak terdaftar mengembalikan URL saat ini, bukan melempar
     *   exception: satu tautan yang salah tidak boleh merobohkan seluruh
     *   halaman.
     *
     * @param  string            $name       Nama route tujuan.
     * @param  mixed             $parameters Parameter route (model atau array).
     * @param  bool              $keepPage   Bawa juga nomor halaman.
     * @return string
     */
    function ctx_route(string $name, mixed $parameters = [], bool $keepPage = false): string
    {
        $route = Route::getRoutes()->getByName($name);

        if ($route === null) {
            return request()->fullUrl();
        }

        $routeParameters = is_array($parameters) ? $parameters : [$parameters];

        // `page` dibuang kecuali diminta; nama parameter path dibuang supaya
        // query string tidak dapat menimpa tujuan route.
        $except = $route->parameterNames();

        if (! $keepPage) {
            $except[] = 'page';
        }

        $carried = collect(request()->query())
            ->except($except)
            ->all();

        return route($name, array_merge($routeParameters, $carried));
    }
}

if (! function_exists('ctx_has_filter')) {
    /**
     * Apakah request saat ini membawa filter aktif (parameter apa pun selain
     * `page`). Dipakai untuk memutuskan apakah tombol "Reset filter" perlu
     * dirender — tombol reset pada daftar yang belum difilter hanya menambah
     * kebisingan.
     */
    function ctx_has_filter(): bool
    {
        return collect(request()->query())
            ->keys()
            ->reject(fn (string $key): bool => $key === 'page')
            ->isNotEmpty();
    }
}

if (! function_exists('ctx_back_url')) {
    /**
     * URL "kembali ke daftar" yang aman.
     *
     * Diutamakan URL daftar yang diberikan pemanggil (sudah membawa filter
     * lewat ctx_route(..., keepPage: true)). Bila tidak ada, dipakai referer
     * request — yang selalu berupa halaman GET, karena setiap mutation di
     * aplikasi ini mengikuti PRG. Bila keduanya tidak ada, jatuh ke halaman
     * awal role, bukan ke '/' yang akan me-redirect ke login.
     */
    function ctx_back_url(?string $fallback = null): string
    {
        $referer = request()->headers->get('referer');

        if (is_string($referer) && $referer !== '' && str_starts_with($referer, request()->getSchemeAndHttpHost())) {
            return $referer;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        $user = request()->user();

        return $user?->homeUrl() ?? route('login');
    }
}
