@extends('layouts.app')
@section('title', 'Submit Laporan')

@section('content')
<div class="container py-4" style="max-width: 800px;">
    <div class="mb-4">
        <h4 class="mb-1 fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Submit Laporan Kelas</h4>
        <p class="text-muted small">Isi formulir di bawah ini untuk melaporkan aktivitas kelas yang telah selesai.</p>
    </div>

    {{-- Tampilkan error validasi --}}
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

    <form method="POST" action="{{ route('coach.reports.store') }}" enctype="multipart/form-data" id="reportForm">
        @csrf

        {{-- BAGIAN 1: Informasi Kelas --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom border-light">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-geo-alt-fill text-primary me-2"></i> Informasi Kelas</span>
            </div>
            <div class="card-body row g-4 p-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Pilih Kelas <span class="text-danger">*</span></label>
                    <select name="class_id" id="classSelect" class="form-select bg-light" required>
                        <option value="">— Pilih Kelas —</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ old('class_id') == $class->id ? 'selected' : '' }}>
                                {{ $class->school->name }} — {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small mt-2">
                        Siswa belum muncul di absensi? 
                        <a href="#" id="linkKelolaKelas" class="text-decoration-none fw-medium" style="display:none">
                            <i class="bi bi-people"></i> Kelola siswa kelas ini &rarr;
                        </a>
                        <span id="infoKelolaKelas" class="text-muted">
                            Pilih kelas dulu untuk kelola siswa.
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Tanggal Kegiatan <span class="text-danger">*</span></label>
                    <input type="date" name="report_date" class="form-control bg-light" value="{{ old('report_date', date('Y-m-d')) }}" required>
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
                    <input type="text" name="lesson_material" class="form-control bg-light" value="{{ old('lesson_material') }}" placeholder="Contoh: Penjumlahan dan Pengurangan Pecahan" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">Ringkasan Kegiatan <span class="text-danger">*</span></label>
                    <textarea name="activity_summary" class="form-control bg-light" rows="4" placeholder="Ceritakan apa yang terjadi selama kelas berlangsung..." required>{{ old('activity_summary') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" class="form-control bg-light" rows="2" placeholder="Tuliskan catatan khusus atau rencana tindak lanjut jika ada...">{{ old('notes') }}</textarea>
                </div>
                
                {{-- UPLOAD FOTO --}}
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small"><i class="bi bi-camera me-1"></i> Foto Kegiatan</label>
                    <input type="file" name="photos[]" class="form-control bg-light" accept="image/*" multiple>
                    <div class="form-text small">
                        Bisa pilih lebih dari 1 foto sekaligus. Maksimal <strong>10 foto</strong>.
                    </div>
                    <div id="photoPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>

                {{-- UPLOAD VIDEO --}}
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small"><i class="bi bi-camera-video me-1"></i> Video Kegiatan</label>
                    <input type="file" name="videos[]" class="form-control bg-light" accept="video/*" multiple>
                    <div class="form-text small">
                        Bisa pilih lebih dari 1 video. Maksimal <strong>3 video</strong>.
                        Format: MP4, MOV, AVI, dll.
                    </div>
                    <div id="videoPreview" class="mt-3"></div>
                </div>
            </div>
        </div>

        {{-- BAGIAN 3: Absensi Siswa (muncul setelah pilih kelas) --}}
        <div class="card shadow-sm border-0 mb-4" id="attendanceCard" style="display:none">
            <div class="card-header bg-white py-3 border-bottom border-light">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Absensi Siswa</span>
            </div>
            <div class="card-body p-4">
                <div id="attendanceList">
                    {{-- Diisi oleh JavaScript --}}
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mt-5">
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-send-fill"></i> Kirim Laporan
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
// Ketika coach memilih kelas, load daftar siswa via AJAX
document.getElementById('classSelect').addEventListener('change', function () {
    const classId = this.value;
    const card    = document.getElementById('attendanceCard');
    const list    = document.getElementById('attendanceList');
    const link = document.getElementById('linkKelolaKelas');
    const info = document.getElementById('infoKelolaKelas');
    
    if (classId) {
        link.href = `/classes/${classId}/students`;
        link.style.display = 'inline';
        info.style.display = 'none';
    } else {
        link.style.display = 'none';
        info.style.display = 'inline';
    }

    if (!classId) {
        card.style.display = 'none';
        return;
    }
    
    // Tampilkan loading
    list.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary"></div> Memuat daftar siswa...</div>';
    card.style.display = 'block';

    // Fetch siswa dari server
    fetch(`/api/classes/${classId}/students`)
        .then(res => res.json())
        .then(students => {
            if (students.length === 0) {
                list.innerHTML = `
                    <div class="text-center py-4 bg-light rounded-3">
                        <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-2"></i>
                        <p class="text-muted mb-0">Tidak ada siswa terdaftar di kelas ini.</p>
                    </div>`;
                return;
            }

            const statuses = ['present', 'absent', 'sick', 'permission'];
            const labels   = { present: 'Hadir', absent: 'Absen', sick: 'Sakit', permission: 'Izin' };

            let html = `
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Nama Siswa</th>
                            ${statuses.map(s => `<th class="text-center text-secondary fw-semibold">${labels[s]}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
            `;

            students.forEach(student => {
                html += `<tr><td class="fw-medium">${student.name}</td>`;
                statuses.forEach((s, i) => {
                    html += `
                        <td class="text-center">
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="radio"
                                       name="attendance[${student.id}]"
                                       value="${s}"
                                       ${i === 0 ? 'checked' : ''} style="cursor:pointer">
                            </div>
                        </td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            list.innerHTML = html;
        })
        .catch(() => {
            list.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal memuat data siswa. Silakan coba lagi.
                </div>`;
        });
});

// Preview foto sebelum upload
document.querySelector('input[name="photos[]"]').addEventListener('change', function () {
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '';

    if (this.files.length > 10) {
        alert('Maksimal 10 foto!');
        this.value = '';
        return;
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

// Preview nama video sebelum upload
document.querySelector('input[name="videos[]"]').addEventListener('change', function () {
    const preview = document.getElementById('videoPreview');
    preview.innerHTML = '';

    if (this.files.length > 3) {
        alert('Maksimal 3 video!');
        this.value = '';
        return;
    }

    Array.from(this.files).forEach((file, i) => {
        preview.innerHTML += `
            <span class="badge bg-light text-dark border border-secondary-subtle py-2 px-3 me-2 mb-2">
                <i class="bi bi-film text-danger me-1"></i> ${file.name}
                <span class="ms-1 text-muted fw-normal">(${(file.size / 1024 / 1024).toFixed(1)} MB)</span>
            </span>`;
    });
});
</script>
@endsection