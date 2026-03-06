@extends('layouts.app')
@section('title', 'Laporan Saya')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📋 Laporan Saya</h4>
        <a href="{{ route('coach.reports.create') }}" class="btn btn-primary">
            + Submit Laporan Baru
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Sekolah</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>{{ $report->report_date->format('d M Y') }}</td>
                        <td>{{ $report->school->name }}</td>
                        <td>{{ $report->schoolClass->name }}</td>
                        <td>
                            @php
                                $colors = [
                                    'draft'     => 'secondary',
                                    'submitted' => 'warning',
                                    'approved'  => 'success',
                                    'rejected'  => 'danger',
                                ];
                            @endphp
                            <span class="badge bg-{{ $colors[$report->status] }}">
                                {{ ucfirst($report->status) }}
                            </span>

                            {{-- Tampilkan alasan penolakan --}}
                            @if($report->status === 'rejected' && $report->admin_notes)
                                <br>
                                <small class="text-danger">{{ $report->admin_notes }}</small>
                            @endif
                        </td>
                        <td>
                            @if(in_array($report->status, ['draft', 'rejected']))
                                <a href="{{ route('coach.reports.edit', $report) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Edit
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Belum ada laporan. Klik "Submit Laporan Baru" untuk mulai.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
            <div class="card-footer">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection