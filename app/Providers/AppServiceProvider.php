<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Perbaikan 2026-09-24: default Laravel adalah "pagination::tailwind",
        // sedangkan aplikasi ini memakai Bootstrap 5. Markup Tailwind di
        // halaman Bootstrap membuat ikon chevron SVG mentah dan tautan tanpa
        // gaya sehingga simbol paginasi tampak rusak/tidak terbaca. Satu
        // perbaikan di sini berlaku untuk SEMUA halaman yang memakai
        // ->links(); tidak ada halaman yang boleh menulis paginasi sendiri.
        Paginator::useBootstrapFive();
    }
}