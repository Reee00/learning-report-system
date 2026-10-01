@extends('layouts.app')
@section('title', 'Edit Laporan')

@section('content')
<div class="container py-4" style="max-width: 800px;">
    <div class="mb-4">
                    <x-breadcrumb :items="[
                ['label' => 'Laporan Saya', 'url' => route('coach.reports.index')],
                ['label' => 'Edit Laporan'],
            ]" />
<h1 class="page-title">Edit Laporan Kelas</h1>
        <p class="text-muted small">Perbarui informasi laporan kelas Anda.</p>
    </div>

    @if($report->status === 'rejected')
        <div class="alert alert-danger shadow-sm border-0 d-flex mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger mt-1"></i>
            <div>
                <strong class="d-block mb-1 text-danger">Laporan Ditolak (Revisi Diperlukan)</strong>
                <p class="mb-0 text-dark">{{ $report->admin_notes }}</p>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
            <div>
                <strong class="d-block mb-1">Periksa kembali isian Anda:</strong>
                <ul class="mb-0 ps-3 small">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('coach.reports.update', $report) }}" enctype="multipart/form-data" id="reportForm">
        @csrf
        @method('PUT')

        {{-- BAGIAN 1: Informasi Kelas --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom border-light">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-geo-alt-fill text-primary me-2"></i> Informasi Kelas</span>
            </div>
            <div class="card-body row g-4 p-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Kelas <span class="text-danger">*</span></label>
                    <select name="class_id" class="form-select bg-light" required>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ $report->class_id == $class->id ? 'selected' : '' }}>
                                {{ $class->school->name }} — {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Tanggal Kegiatan <span class="text-danger">*</span></label>
                    <input type="date" name="report_date" class="form-control bg-light" value="{{ $report->report_date->format('Y-m-d') }}" required>
                </div>
            </div>
        </div>

        {{-- BAGIAN 2: Isi Laporan --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom border-light">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-journal-text text-primary me-2"></i> Isi Laporan</span>
            </div>
            <div class="card-body row g-4 p-4">
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">Materi Pelajaran <span class="text-danger">*</span></label>
                    <input type="text" name="lesson_material" class="form-control bg-light" value="{{ old('lesson_material', $report->lesson_material) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">Goals Materi <span class="text-danger">*</span></label>
                    <textarea name="goals_materi" class="form-control bg-light" rows="6" placeholder="Goals:
- Memahami konsep storytelling visual dalam komik.
- Mengenal bagaimana AI dapat membantu membuat komik secara cepat.
- Siswa melakukan praktik dan menyusun cerita sesuai dengan konsep yang telah dibuat." required>{{ old('goals_materi', $report->goals_materi) }}</textarea>
                    <div class="form-text small">Tuliskan goals pembelajaran pertemuan ini. <em># Bisa disesuaikan dengan goals yang dilakukan siswa pada pertemuan.</em></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">Activity Report <span class="text-danger">*</span></label>
                    <textarea name="activity_report" class="form-control bg-light" rows="6" placeholder="Progress:
- Murid diminta untuk membuat komik dengan memanfaatkan GEM di Gemini dan teknik ANTIF dalam prompting AI.
- Murid menentukan komik sesuai keinginan dan kreativitas mereka sendiri.
- Murid dapat memahami dan mengikuti kelas dengan baik." required>{{ old('activity_report', $report->activity_report) }}</textarea>
                    <div class="form-text small">Ceritakan aktivitas/progress siswa selama kelas berlangsung. <em># Bisa disesuaikan dengan aktivitas siswa pada pertemuan.</em></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">Kendala Teknis Saat Kelas Berjalan</label>
                    <textarea name="notes" class="form-control bg-light" rows="2">{{ old('notes', $report->notes) }}</textarea>
                </div>

                {{-- FOTO EXISTING --}}
                @if($report->photos->count() > 0)
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small"><i class="bi bi-images me-1"></i> Foto Saat Ini</label>
                    <p class="small text-muted mb-2">Centang foto yang ingin dihapus.</p>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($report->photos as $photo)
                        <div class="position-relative">
                            <div class="overflow-hidden rounded-3 shadow-sm border" style="width: 100px; height: 100px;">
                                <img src="{{ $photo->url() }}" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="position-absolute top-0 end-0 m-1 bg-white rounded shadow-sm px-1 py-0 border">
                                <div class="form-check m-0">
                                    <input class="form-check-input mt-1" type="checkbox" name="delete_media[]" value="{{ $photo->id }}" id="delPhoto{{ $photo->id }}" style="cursor:pointer">
                                    <label class="form-check-label text-danger small fw-medium" for="delPhoto{{ $photo->id }}" style="cursor:pointer; font-size:10px;">Hapus</label>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- UPLOAD FOTO BARU --}}
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">
                        <i class="bi bi-camera me-1"></i> Tambah Foto Baru
                        <span class="fw-normal text-muted ms-1">(sudah ada {{ $report->photos->count() }}/10)</span>
                    </label>
                    <input type="file" name="photos[]" class="form-control bg-light" accept="image/*" multiple>
                    <div id="photoPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>

                {{-- VIDEO EXISTING --}}
                @if($report->videos->count() > 0)
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small"><i class="bi bi-camera-video me-1"></i> Video Saat Ini</label>
                    <p class="small text-muted mb-2">Centang video yang ingin dihapus.</p>
                    <div class="d-flex flex-column gap-2">
                        @foreach($report->videos as $video)
                        <div class="d-flex align-items-center justify-content-between p-2 border rounded-3 bg-light">
                            <div class="d-flex align-items-center text-truncate">
                                <i class="bi bi-film text-primary me-2"></i>
                                <span class="text-dark small text-truncate" style="max-width: 200px;">{{ $video->original_name ?? 'Video ' . $loop->iteration }}</span>
                            </div>
                            <div class="form-check m-0">
                                <input class="form-check-input mt-1" type="checkbox" name="delete_media[]" value="{{ $video->id }}" id="delVideo{{ $video->id }}" style="cursor:pointer">
                                <label class="form-check-label text-danger small fw-medium" for="delVideo{{ $video->id }}" style="cursor:pointer">Hapus</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- UPLOAD VIDEO BARU --}}
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">
                        <i class="bi bi-camera-video me-1"></i> Tambah Video Baru
                        <span class="fw-normal text-muted ms-1">(sudah ada {{ $report->videos->count() }}/3)</span>
                    </label>
                    <input type="file" name="videos[]" class="form-control bg-light" accept="video/*" multiple>
                    <div id="videoPreview" class="mt-3"></div>
                </div>

                {{-- BUKTI ABSENSI EXISTING --}}
                @if($report->attendanceMedia->count() > 0)
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small"><i class="bi bi-clipboard-check me-1"></i> Bukti Absensi Saat Ini</label>
                    <p class="small text-muted mb-2">Centang foto yang ingin dihapus.</p>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($report->attendanceMedia as $att)
                        <div class="position-relative">
                            <div class="overflow-hidden rounded-3 shadow-sm border" style="width: 100px; height: 100px;">
                                <img src="{{ $att->url() }}" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="position-absolute top-0 end-0 m-1 bg-white rounded shadow-sm px-1 py-0 border">
                                <div class="form-check m-0">
                                    <input class="form-check-input mt-1" type="checkbox" name="delete_media[]" value="{{ $att->id }}" id="delAtt{{ $att->id }}" style="cursor:pointer">
                                    <label class="form-check-label text-danger small fw-medium" for="delAtt{{ $att->id }}" style="cursor:pointer; font-size:10px;">Hapus</label>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- UPLOAD BUKTI ABSENSI BARU --}}
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">
                        <i class="bi bi-clipboard-check me-1"></i> Tambah Bukti Absensi
                        <span class="fw-normal text-muted ms-1">(sudah ada {{ $report->attendanceMedia->count() }}/5)</span>
                    </label>
                    <input type="file" name="attendance_media[]" class="form-control bg-light" accept="image/*" multiple>
                    <div class="form-text small">Foto daftar hadir atau bukti kehadiran lainnya. Maksimal 5 foto (masing-masing maks. 10 MB).</div>
                    <div id="attendancePreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>
            </div>
        </div>

        {{-- BAGIAN 3: Absensi --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom border-light">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Absensi Siswa</span>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-secondary fw-semibold">Nama Siswa</th>
                                <th class="text-center text-secondary fw-semibold">Hadir</th>
                                <th class="text-center text-secondary fw-semibold">Absen</th>
                                <th class="text-center text-secondary fw-semibold">Sakit</th>
                                <th class="text-center text-secondary fw-semibold">Izin</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($students as $student)
                            @php
                                $currentStatus = $attendances[$student->id]->status ?? 'present';
                            @endphp
                            <tr>
                                <td class="fw-medium">{{ $student->name }}</td>
                                @foreach(['present','absent','sick','permission'] as $s)
                                    <td class="text-center">
                                        <div class="form-check form-check-inline m-0">
                                            <input class="form-check-input" type="radio" name="attendance[{{ $student->id }}]" value="{{ $s }}" {{ $currentStatus === $s ? 'checked' : '' }} style="cursor:pointer">
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mt-5 action-bar">
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-send-fill"></i> Simpan & Kirim Ulang
            </button>
            <a href="{{ route('coach.reports.index') }}" class="btn btn-light border px-4 py-2 fw-medium text-secondary">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
// Batas ukuran file — harus sinkron dengan validasi server (Coach\ReportController).
const MAX_VIDEO_MB = 100;
const MAX_PHOTO_MB = 10;
const MAX_PHOTOS   = 10;
const MAX_ATTENDANCE = 5;

function bindImagePreview(selector, previewId, maxCount, maxMb) {
    const input = document.querySelector(selector);
    if (!input) return;

    input.addEventListener('change', function () {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';

        if (this.files.length > maxCount) {
            alert(`Maksimal ${maxCount} foto!`);
            this.value = '';
            return;
        }

        for (const file of this.files) {
            if (file.size > maxMb * 1024 * 1024) {
                alert(`Foto "${file.name}" melebihi ${maxMb} MB. Kecilkan ukuran file atau pilih foto lain.`);
                this.value = '';
                preview.innerHTML = '';
                return;
            }
        }

        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                preview.innerHTML += `
                    <div class="position-relative overflow-hidden shadow-sm" style="width: 80px; height: 80px; border-radius: 10px; border: 2px solid #fff;">
                        <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>`;
            };
            reader.readAsDataURL(file);
        });
    });
}

function bindVideoPreview(selector, previewId, maxMb) {
    const input = document.querySelector(selector);
    if (!input) return;

    input.addEventListener('change', function () {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';

        for (const file of this.files) {
            if (file.size > maxMb * 1024 * 1024) {
                alert(`Video "${file.name}" berukuran ${(file.size / 1024 / 1024).toFixed(1)} MB, melebihi batas ${maxMb} MB. Kecilkan ukuran video atau pilih video lain.`);
                this.value = '';
                preview.innerHTML = '';
                return;
            }
        }

        Array.from(this.files).forEach(file => {
            preview.innerHTML += `
                <span class="badge bg-light text-dark border border-secondary-subtle py-2 px-3 me-2 mb-2">
                    <i class="bi bi-film text-danger me-1"></i> ${file.name}
                    <span class="ms-1 text-muted fw-normal">(${(file.size / 1024 / 1024).toFixed(1)} MB)</span>
                </span>`;
        });
    });
}

bindImagePreview('input[name="photos[]"]', 'photoPreview', MAX_PHOTOS, MAX_PHOTO_MB);
bindImagePreview('input[name="attendance_media[]"]', 'attendancePreview', MAX_ATTENDANCE, MAX_PHOTO_MB);
bindVideoPreview('input[name="videos[]"]', 'videoPreview', MAX_VIDEO_MB);

// safety belt terakhir: cegah submit jika ada file over-size yang lolos
document.getElementById('reportForm').addEventListener('submit', function (e) {
    const checks = [
        [this.querySelector('input[name="videos[]"]'), MAX_VIDEO_MB],
        [this.querySelector('input[name="photos[]"]'), MAX_PHOTO_MB],
        [this.querySelector('input[name="attendance_media[]"]'), MAX_PHOTO_MB],
    ];
    for (const [input, maxMb] of checks) {
        if (!input || !input.files) continue;
        for (const file of input.files) {
            if (file.size > maxMb * 1024 * 1024) {
                e.preventDefault();
                alert(`File "${file.name}" melebihi batas ${maxMb} MB — laporan belum terkirim.`);
                return;
            }
        }
    }
});
</script>
@endsection
