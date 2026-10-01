@extends('layouts.app')
@section('title', 'Accident Notes')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('coach.reports.index')],
                ['label' => 'Accident Notes'],
            ]" />
<h1 class="page-title">Accident Notes</h1>
            <p class="text-muted small mb-0">
                Pengingat pribadi Anda atas catatan kecelakaan pada laporan yang Anda buat.
            </p>
        </div>
        <a href="{{ route('coach.reports.index') }}" class="btn btn-light border fw-medium text-secondary">
            <i class="bi bi-arrow-left me-2"></i> Laporan Saya
        </a>
    </div>

    {{-- Daftar ini murni isi laporan (reports.notes) milik coach yang login.
         Bukan notification center: tidak ada database notification maupun web
         push yang dibuat dari catatan ini, dan catatan milik coach lain tidak
         pernah muncul di sini. --}}
    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
        <span class="badge bg-light text-secondary border">Pengingat pribadi saya</span>
        <span class="text-muted small">{{ $notes->total() }} catatan</span>
    </div>

    @forelse($notes as $report)
        {{-- Sumber catatan ditampilkan supaya konteksnya jelas, lalu catatannya
             sendiri dirender komponen yang sama dengan halaman detail. --}}
        <div class="small text-muted mb-1 ps-1">
            <i class="bi bi-calendar-event me-1"></i>{{ $report->report_date->format('d M Y') }}
            &bull; <i class="bi bi-building me-1"></i>{{ $report->school->name }}
            &bull; {{ $report->schoolClass->name }}
            @if($report->teachingSchedule?->meeting_number)
                &bull; Pertemuan {{ $report->teachingSchedule->meeting_number }}
            @endif
        </div>

        @include('partials.accident-notes', [
            'notes' => $report->notes,
            'reportId' => $report->id,
            'reportUrl' => route('coach.reports.show', $report),
        ])
    @empty
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-journal-check text-muted opacity-50 mb-3 d-block lh-1" style="font-size: 4rem;" aria-hidden="true"></i>
                <h6 class="text-muted mb-2">Belum ada accident notes.</h6>
                <p class="text-muted small mb-3">
                    Catatan kecelakaan yang Anda isi pada form laporan akan muncul di sini.
                </p>
                <a href="{{ route('coach.reports.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="bi bi-collection me-1"></i> Lihat Laporan Saya
                </a>
            </div>
        </div>
    @endforelse

    @if($notes->hasPages())
        <div class="mt-3">
            {{ $notes->links() }}
        </div>
    @endif
</div>
@endsection
