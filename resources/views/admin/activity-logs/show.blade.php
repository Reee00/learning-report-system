@extends('layouts.app')
@section('title', 'Detail Activity Log')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-clipboard-data text-primary me-2"></i> Detail Activity Log #{{ $log->id }}</h4>
            <p class="text-muted small mb-0">{{ $log->created_at->format('d F Y, H:i:s') }} ({{ $log->created_at->diffForHumans() }})</p>
        </div>
        <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-light border d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <dl class="row mb-0">
                <dt class="col-sm-3 text-muted">User</dt>
                <dd class="col-sm-9">{{ $log->user?->name ?? 'Sistem' }}</dd>

                <dt class="col-sm-3 text-muted">Role</dt>
                <dd class="col-sm-9">{{ \App\Models\User::roleLabels()[$log->user_role] ?? $log->user_role ?? '-' }}</dd>

                <dt class="col-sm-3 text-muted">Action</dt>
                <dd class="col-sm-9"><code>{{ $log->action }}</code></dd>

                <dt class="col-sm-3 text-muted">Subject</dt>
                <dd class="col-sm-9">{{ $log->subject_type ? $log->subject_type . ($log->subject_id ? ' #'.$log->subject_id : '') : '-' }}</dd>

                <dt class="col-sm-3 text-muted">Deskripsi</dt>
                <dd class="col-sm-9">{{ $log->description ?? '-' }}</dd>

                <dt class="col-sm-3 text-muted">IP Address</dt>
                <dd class="col-sm-9">{{ $log->ip_address ?? '-' }}</dd>

                <dt class="col-sm-3 text-muted">User Agent</dt>
                <dd class="col-sm-9"><small class="text-break">{{ $log->user_agent ?? '-' }}</small></dd>
            </dl>
        </div>
    </div>

    @if($log->metadata)
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3">
            <span class="fw-bold fs-6 text-dark"><i class="bi bi-braces text-primary me-2"></i> Metadata</span>
        </div>
        <div class="card-body">
            <pre class="bg-light rounded p-3 small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
    @endif
</div>
@endsection
