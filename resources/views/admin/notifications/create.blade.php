@extends('layouts.app')
@section('title', 'Kirim Notifikasi')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Kirim Notifikasi'],
            ]" />
<h1 class="page-title">Kirim Notifikasi ke Coach</h1>
            <p class="text-muted small mb-0">
                @if(auth()->user()->role === \App\Models\User::ROLE_SCHOOL_PIC)
                    Pilih coach yang mengajar di sekolah Anda.
                @else
                    Pilih coach penerima — semua coach berada dalam scope Anda.
                @endif
            </p>
        </div>
        @if(auth()->user()->role === \App\Models\User::ROLE_SCHOOL_PIC)
        <a href="{{ route('pic.dashboard') }}" class="btn btn-light border d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        @else
        <a href="{{ route('admin.dashboard') }}" class="btn btn-light border d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        @endif
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.notifications.store') }}" class="card shadow-sm border-0">
        @csrf
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Jenis Notifikasi <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" required>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Judul <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="150" required
                           placeholder="cth. Perubahan jadwal SD Harapan Bangsa minggu depan"
                           value="{{ old('title') }}">
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">
                        Coach Penerima <span class="text-danger">*</span>
                        <small class="text-muted fw-normal">— pilih satu atau beberapa (tahan Ctrl/Cmd)</small>
                    </label>
                    <select name="coach_ids[]" class="form-select" multiple size="6" required>
                        @foreach($coaches as $coach)
                            <option value="{{ $coach->id }}"
                                {{ in_array($coach->id, old('coach_ids', [])) ? 'selected' : '' }}>
                                {{ $coach->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($coaches->isEmpty())
                        <div class="form-text text-danger">
                            Tidak ada coach dalam scope Anda. Pastikan coach sudah di-assign ke kelas di sekolah Anda.
                        </div>
                    @endif
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Pesan <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" maxlength="1000" required
                              placeholder="Tulis pesan yang ingin disampaikan ke coach...">{{ old('message') }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Jadwal Terkait <span class="text-muted fw-normal">(opsional)</span></label>
                    <select name="schedule_id" class="form-select">
                        <option value="">— Tanpa jadwal terkait —</option>
                        @foreach($schedules as $schedule)
                            <option value="{{ $schedule->id }}" {{ old('schedule_id') == $schedule->id ? 'selected' : '' }}>
                                {{ $schedule->session_date->format('d M Y') }} — {{ $schedule->school->name }} — {{ $schedule->schoolClass->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Laporan Terkait <span class="text-muted fw-normal">(opsional)</span></label>
                    <select name="report_id" class="form-select">
                        <option value="">— Tanpa laporan terkait —</option>
                        @foreach($reports as $report)
                            <option value="{{ $report->id }}" {{ old('report_id') == $report->id ? 'selected' : '' }}>
                                #{{ $report->id }} — {{ $report->report_date->format('d M Y') }} — {{ $report->school->name }} — {{ $report->schoolClass->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Bila diisi, laporan menggantikan jadwal sebagai tautan notifikasi.</div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2 border-top-0">
            <button type="reset" class="btn btn-light border">Reset</button>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-send me-1"></i> Kirim Notifikasi
            </button>
        </div>
    </form>
</div>
@endsection
