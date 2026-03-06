@extends('layouts.app')
@section('title', 'Master Coach')

@section('content')
<div class="container py-4">
    <h4 class="mb-4">👨‍🏫 Master Data Coach</h4>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Coach</th>
                        <th>Email</th>
                        <th>Kelas yang Di-assign</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($coaches as $coach)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $coach->name }}</td>
                        <td>{{ $coach->email }}</td>
                        <td>
                            @if($coach->coachClasses->isEmpty())
                                <span class="text-muted fst-italic">Belum ada assignment</span>
                            @else
                                @foreach($coach->coachClasses as $cc)
                                    <span class="badge bg-primary me-1">
                                        {{ $cc->schoolClass->school->name }} — {{ $cc->schoolClass->name }}
                                    </span>
                                @endforeach
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.coaches.show', $coach) }}"
                               class="btn btn-sm btn-outline-primary">
                                Kelola Assignment
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Belum ada coach terdaftar.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($coaches->hasPages())
            <div class="card-footer">{{ $coaches->links() }}</div>
        @endif
    </div>
</div>
@endsection