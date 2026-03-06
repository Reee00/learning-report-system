@extends('layouts.app')
@section('title', 'Edit Laporan')

@section('content')
<div class="container py-4">
    <h4 class="mb-2">✏️ Edit Laporan</h4>

    @if($report->status === 'rejected')
        <div class="alert alert-warning mb-4">
            <strong>Laporan Ditolak:</strong> {{ $report->admin_notes }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('coach.reports.update', $report) }}"
          enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header fw-semibold">📍 Informasi Kelas</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Kelas</label>
                    <select name="class_id" class="form-select" required>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}"
                                {{ $report->class_id == $class->id ? 'selected' : '' }}>
                                {{ $class->school->name }} — {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="report_date" class="form-control"
                           value="{{ $report->report_date->format('Y-m-d') }}" required>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">📖 Isi Laporan</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">Materi Pelajaran *</label>
                    <input type="text" name="lesson_material" class="form-control"
                           value="{{ old('lesson_material', $report->lesson_material) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Ringkasan Kegiatan *</label>
                    <textarea name="activity_summary" class="form-control" rows="4" required>{{ old('activity_summary', $report->activity_summary) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan Tambahan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $report->notes) }}</textarea>
                </div>
{{-- FOTO EXISTING --}}
@if($report->photos->count() > 0)
<div class="col-12">
    <label class="form-label">📷 Foto Saat Ini (centang untuk hapus)</label>
    <div class="d-flex flex-wrap gap-2">
        @foreach($report->photos as $photo)
        <div class="position-relative">
            <img src="{{ $photo->url() }}"
                 style="width:100px;height:100px;object-fit:cover;border-radius:8px;">
            <div class="form-check position-absolute top-0 end-0 bg-white rounded p-1">
                <input class="form-check-input" type="checkbox"
                       name="delete_media[]"
                       value="{{ $photo->id }}"
                       id="delPhoto{{ $photo->id }}">
                <label class="form-check-label text-danger small"
                       for="delPhoto{{ $photo->id }}">Hapus</label>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- UPLOAD FOTO BARU --}}
<div class="col-12">
    <label class="form-label">
        📷 Tambah Foto Baru
        <span class="text-muted small">
            (sudah ada {{ $report->photos->count() }}/10 foto)
        </span>
    </label>
    <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
    <div id="photoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
</div>

{{-- VIDEO EXISTING --}}
@if($report->videos->count() > 0)
<div class="col-12">
    <label class="form-label">🎥 Video Saat Ini (centang untuk hapus)</label>
    @foreach($report->videos as $video)
    <div class="d-flex align-items-center gap-2 mb-2 p-2 border rounded">
        <span>🎥 {{ $video->original_name ?? 'Video ' . $loop->iteration }}</span>
        <div class="form-check ms-auto">
            <input class="form-check-input" type="checkbox"
                   name="delete_media[]"
                   value="{{ $video->id }}"
                   id="delVideo{{ $video->id }}">
            <label class="form-check-label text-danger small"
                   for="delVideo{{ $video->id }}">Hapus</label>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- UPLOAD VIDEO BARU --}}
<div class="col-12">
    <label class="form-label">
        🎥 Tambah Video Baru
        <span class="text-muted small">
            (sudah ada {{ $report->videos->count() }}/3 video)
        </span>
    </label>
    <input type="file" name="videos[]" class="form-control" accept="video/*" multiple>
    <div id="videoPreview" class="mt-2"></div>
</div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">👥 Absensi Siswa</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Siswa</th>
                                <th class="text-center">Hadir</th>
                                <th class="text-center">Absen</th>
                                <th class="text-center">Sakit</th>
                                <th class="text-center">Izin</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($students as $student)
                            @php
                                $currentStatus = $attendances[$student->id]->status ?? 'present';
                            @endphp
                            <tr>
                                <td>{{ $student->name }}</td>
                                @foreach(['present','absent','sick','permission'] as $s)
                                    <td class="text-center">
                                        <input type="radio"
                                               name="attendance[{{ $student->id }}]"
                                               value="{{ $s }}"
                                               {{ $currentStatus === $s ? 'checked' : '' }}>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">💾 Simpan & Kirim Ulang</button>
            <a href="{{ route('coach.reports.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>
@endsection