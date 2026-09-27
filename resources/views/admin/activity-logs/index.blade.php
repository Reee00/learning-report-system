@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-clipboard-data text-primary me-2"></i> Activity Log</h4>
            <p class="text-muted small mb-0">Aktivitas semua role — retensi 7 hari, dihapus otomatis setiap malam.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-light border d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    {{-- Filter --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">User</label>
                    <select name="user_id" class="form-select">
                        <option value="">Semua User</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold">Role</label>
                    <select name="role" class="form-select">
                        <option value="">Semua Role</option>
                        @foreach(\App\Models\User::roleLabels() as $key => $label)
                            <option value="{{ $key }}" {{ request('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">Action</label>
                    <select name="action" class="form-select">
                        <option value="">Semua Action</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold">Dari</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold">Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">Kata kunci</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="deskripsi / action / subject">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary px-4"><i class="bi bi-search me-1"></i> Filter</button>
                    <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-light border" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <span class="fw-bold fs-6 text-dark">
                Log Aktivitas
                <span class="badge bg-primary rounded-pill ms-2">{{ $logs->total() }} entri</span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold ps-4">Waktu</th>
                        <th class="text-secondary fw-semibold">User</th>
                        <th class="text-secondary fw-semibold">Action</th>
                        <th class="text-secondary fw-semibold">Subject</th>
                        <th class="text-secondary fw-semibold">Deskripsi</th>
                        <th class="text-secondary fw-semibold">IP</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 80px;">Detail</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-medium text-dark small">{{ $log->created_at->format('d M H:i') }}</div>
                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            <div class="fw-medium small">{{ $log->user?->name ?? 'Sistem' }}</div>
                            @if($log->user_role)
                                <span class="badge bg-secondary-subtle text-secondary">{{ \App\Models\User::roleLabels()[$log->user_role] ?? $log->user_role }}</span>
                            @endif
                        </td>
                        <td><code class="small">{{ $log->action }}</code></td>
                        <td class="small">
                            @if($log->subject_type)
                                {{ $log->subject_type }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="small text-wrap">{{ $log->description ?? '-' }}</td>
                        <td class="small text-muted">{{ $log->ip_address ?? '-' }}</td>
                        <td class="text-center">
                            <a href="{{ route('admin.activity-logs.show', $log) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bi bi-clipboard-x fs-1 text-muted opacity-50 mb-2 d-block"></i>
                            <h6 class="text-muted mb-0">Tidak ada aktivitas yang cocok dengan filter.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
