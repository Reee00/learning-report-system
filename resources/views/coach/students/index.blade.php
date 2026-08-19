@extends('layouts.app')
@section('title', 'Kelas Saya — Data Siswa')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold">
                <i class="bi bi-people text-primary me-2"></i> Kelas Saya
            </h4>
            <p class="text-muted small mb-0">
                Pilih kelas untuk melihat dan menambahkan data siswa.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body p-3">
            <form action="{{ route('coach.students.index') }}" method="GET" class="mb-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama kelas atau sekolah..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('coach.students.index') }}" class="btn btn-outline-secondary" title="Reset Search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary px-3 fw-medium">Search</button>
                </div>
            </form>
        </div>
    </div>

    @if($classes->isEmpty())
        <div class="card text-center py-5">
            <div class="card-body">
                <div class="fs-1 mb-3">📋</div>
                <h5 class="text-muted">Belum ada kelas yang di-assign ke Anda.</h5>
                <p class="text-muted small">Hubungi SPV Coach untuk mendapatkan assignment kelas.</p>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($classes as $class)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm" style="transition: transform 0.15s, box-shadow 0.15s;"
                         onmouseenter="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(37,99,235,0.12)'"
                         onmouseleave="this.style.transform='';this.style.boxShadow=''">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start mb-3">
                                <div class="rounded-3 bg-primary bg-opacity-10 p-2 me-3 flex-shrink-0">
                                    <i class="bi bi-easel2 text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark">{{ $class->name }}</h6>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-building me-1"></i>{{ $class->school->name }}
                                    </p>
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-light text-secondary border border-secondary-subtle me-2">
                                    <i class="bi bi-people-fill me-1"></i>
                                    {{ $class->students()->count() }} siswa
                                </span>
                            </div>

                            <div class="mt-auto">
                                <a href="{{ route('students.show', $class) }}"
                                   class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2"
                                   id="btn-class-{{ $class->id }}">
                                    <i class="bi bi-person-plus-fill"></i>
                                    Kelola Siswa
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
