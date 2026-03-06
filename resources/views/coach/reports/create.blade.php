@extends('layouts.app')
@section('title', 'Submit Laporan')

@section('content')
<div class="container py-4">
    <h4 class="mb-4">📝 Submit Laporan Kelas</h4>

    {{-- Tampilkan error validasi --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('coach.reports.store') }}"
          enctype="multipart/form-data"
          id="reportForm">
        @csrf

        {{-- BAGIAN 1: Informasi Kelas --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">📍 Informasi Kelas</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                    <select name="class_id" id="classSelect" class="form-select" required>
                        <option value="">— Pilih Kelas —</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}"
                                {{ old('class_id') == $class->id ? 'selected' : '' }}>
                                {{ $class->school->name }} — {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
        Siswa belum muncul di absensi? 
        <a href="#" id="linkKelolaKelas" style="display:none">
            👥 Kelola siswa kelas ini →
        </a>
        <span id="infoKelolaKelas" class="text-muted">
            Pilih kelas dulu untuk kelola siswa.
        </span>
    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date"
                           name="report_date"
                           class="form-control"
                           value="{{ old('report_date', date('Y-m-d')) }}"
                           required>
                </div>
            </div>
        </div>

        {{-- BAGIAN 2: Isi Laporan --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">📖 Isi Laporan</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">Materi Pelajaran <span class="text-danger">*</span></label>
                    <input type="text"
                           name="lesson_material"
                           class="form-control"
                           value="{{ old('lesson_material') }}"
                           placeholder="Contoh: Penjumlahan dan Pengurangan Pecahan"
                           required>
                </div>
                <div class="col-12">
                    <label class="form-label">Ringkasan Kegiatan <span class="text-danger">*</span></label>
                    <textarea name="activity_summary"
                              class="form-control"
                              rows="4"
                              placeholder="Ceritakan apa yang terjadi selama kelas..."
                              required>{{ old('activity_summary') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan Tambahan</label>
                    <textarea name="notes"
                              class="form-control"
                              rows="2"
                              placeholder="Catatan opsional atau tindak lanjut...">{{ old('notes') }}</textarea>
                </div>
{{-- UPLOAD FOTO --}}
<div class="col-12">
    <label class="form-label">📷 Foto Kegiatan</label>
    <input type="file"
           name="photos[]"
           class="form-control"
           accept="image/*"
           multiple>
    <div class="form-text">
        Bisa pilih lebih dari 1 foto sekaligus. Maksimal <strong>10 foto</strong>.
    </div>

    {{-- Preview foto sebelum upload --}}
    <div id="photoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
</div>

{{-- UPLOAD VIDEO --}}
<div class="col-12">
    <label class="form-label">🎥 Video Kegiatan</label>
    <input type="file"
           name="videos[]"
           class="form-control"
           accept="video/*"
           multiple>
    <div class="form-text">
        Bisa pilih lebih dari 1 video. Maksimal <strong>3 video</strong>.
        Format: MP4, MOV, AVI, MKV, dll.
    </div>

    {{-- Preview nama video sebelum upload --}}
    <div id="videoPreview" class="mt-2"></div>
</div>
            </div>
        </div>

        {{-- BAGIAN 3: Absensi Siswa (muncul setelah pilih kelas) --}}
        <div class="card mb-4" id="attendanceCard" style="display:none">
            <div class="card-header fw-semibold">👥 Absensi Siswa</div>
            <div class="card-body">
                <div id="attendanceList">
                    {{-- Diisi oleh JavaScript --}}
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                📤 Kirim Laporan
            </button>
            <a href="{{ route('coach.reports.index') }}" class="btn btn-outline-secondary">
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
                <div class="position-relative">
                    <img src="${e.target.result}"
                         style="width:80px;height:80px;object-fit:cover;border-radius:6px;">
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
            <div class="badge bg-secondary me-1 mb-1 p-2">
                🎥 ${file.name}
                <span class="ms-1 text-warning">(${(file.size / 1024 / 1024).toFixed(1)} MB)</span>
            </div>`;
    });
});

    // Tampilkan loading
    list.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Memuat siswa...</div>';
    card.style.display = 'block';

    // Fetch siswa dari server
    fetch(`/api/classes/${classId}/students`)
        .then(res => res.json())
        .then(students => {
            if (students.length === 0) {
                list.innerHTML = '<p class="text-muted">Tidak ada siswa di kelas ini.</p>';
                return;
            }

            const statuses = ['present', 'absent', 'sick', 'permission'];
            const labels   = { present: 'Hadir', absent: 'Absen', sick: 'Sakit', permission: 'Izin' };

            let html = `
                <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Siswa</th>
                            ${statuses.map(s => `<th class="text-center">${labels[s]}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
            `;

            students.forEach(student => {
                html += `<tr><td>${student.name}</td>`;
                statuses.forEach((s, i) => {
                    html += `
                        <td class="text-center">
                            <input type="radio"
                                   name="attendance[${student.id}]"
                                   value="${s}"
                                   ${i === 0 ? 'checked' : ''}>
                        </td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            list.innerHTML = html;
        })
        .catch(() => {
            list.innerHTML = '<p class="text-danger">Gagal memuat siswa. Coba lagi.</p>';
        });
});
</script>
@endsection