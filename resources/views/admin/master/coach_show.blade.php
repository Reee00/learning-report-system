@extends('layouts.app')
@section('title', 'Kelola Assignment Coach')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canAssignCoach = $currentUser && $authorization->allows($currentUser, 'coaches.assign');
    $canReassignCoach = $currentUser && $authorization->allows($currentUser, 'coaches.reassign');
@endphp
<div class="container py-4">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="mb-4">
        <a href="{{ route('admin.coaches.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            ← Kembali ke Daftar Coach
        </a>
        <h4 class="mb-0">👨‍🏫 {{ $coach->name }}</h4>
        <p class="text-muted">{{ $coach->email }}</p>
    </div>

    <div class="row g-4">

        {{-- KOLOM KIRI: Kelas yang sudah di-assign --}}
        <div class="col-md-7">
            <div class="card">
                <div class="card-header fw-semibold">
                    ✅ Kelas yang Sudah Di-assign
                    <span class="badge bg-primary ms-1">{{ $coach->coachClasses->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if($coach->coachClasses->isEmpty())
                        <div class="text-center text-muted py-4">
                            Belum ada kelas yang di-assign ke coach ini.
                        </div>
                    @else
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Sekolah</th>
                                    <th>Kelas</th>
                                    @if($canReassignCoach)
                                        <th>Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($coach->coachClasses as $cc)
                                <tr>
                                    <td>{{ $cc->schoolClass->school->name }}</td>
                                    <td>{{ $cc->schoolClass->name }}</td>
                                    @if($canReassignCoach)
                                        <td>
                                            <form method="POST"
                                                  action="{{ route('admin.coaches.unassign', [$coach, $cc]) }}"
                                                  onsubmit="return confirm('Hapus assignment ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">
                                                    Hapus
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: Assign ke kelas baru --}}
        <div class="col-md-5">
            <div class="card">
                <div class="card-header fw-semibold">➕ Assign ke Kelas Baru</div>
                <div class="card-body">
                    @if($availableClasses->isEmpty())
                        <p class="text-muted fst-italic">
                            Semua kelas sudah di-assign ke coach ini.
                        </p>
                    @elseif($canAssignCoach)
                        <form method="POST" action="{{ route('admin.coaches.assign', $coach) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Pilih Kelas</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">— Pilih Sekolah & Kelas —</option>
                                    @foreach($availableClasses as $schoolName => $classes)
                                        <optgroup label="{{ $schoolName }}">
                                            @foreach($classes as $class)
                                                <option value="{{ $class->id }}">
                                                    {{ $class->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Kelas yang sudah di-assign tidak ditampilkan.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                Assign Kelas
                            </button>
                        </form>
                    @else
                        <p class="text-muted mb-0">Anda tidak memiliki permission untuk melakukan assignment.</p>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
