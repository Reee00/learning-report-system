{{--
    Komponen paginasi bersama (perbaikan 2026-09-24).

    Root cause tampilan paginasi yang rusak:
    1. Laravel default-nya memakai "pagination::tailwind" — markup Tailwind di
       aplikasi Bootstrap 5 (sekarang diperbaiki lewat Paginator::useBootstrapFive()).
    2. View bawaan Bootstrap 5 memakai @lang('pagination.previous'),
       __('Showing'), __('to'), __('of'), __('results') — aplikasi ini tidak
       punya folder lang/, sehingga string mentah "pagination.previous",
       "Showing", "of", "results" tercetak apa adanya di halaman.

    View ini menggantikan view bawaan untuk SELURUH halaman yang memakai
    ->links(): label Indonesia eksplisit, ikon Bootstrap Icons (bukan entitas
    &lsaquo;/&rsaquo; yang mudah salah baca), status aktif/disabled yang jelas,
    dan nomor halaman yang tidak pernah terpecah antar digit.
--}}
@if ($paginator->hasPages())
    <nav class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3"
         aria-label="Navigasi halaman">
        <p class="small text-muted mb-0 text-center text-sm-start">
            Menampilkan
            <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
            &ndash;
            <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
            dari
            <span class="fw-semibold">{{ $paginator->total() }}</span>
            data
        </p>

        <ul class="pagination mb-0">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true">
                        <i class="bi bi-chevron-left me-1"></i><span class="d-none d-sm-inline">Sebelumnya</span>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       aria-label="Halaman sebelumnya">
                        <i class="bi bi-chevron-left me-1"></i><span class="d-none d-sm-inline">Sebelumnya</span>
                    </a>
                </li>
            @endif

            {{-- Nomor halaman --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}"
                                   aria-label="Buka halaman {{ $page }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next"
                       aria-label="Halaman berikutnya">
                        <span class="d-none d-sm-inline">Berikutnya</span><i class="bi bi-chevron-right ms-1"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true">
                        <span class="d-none d-sm-inline">Berikutnya</span><i class="bi bi-chevron-right ms-1"></i>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
