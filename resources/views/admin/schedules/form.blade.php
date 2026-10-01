@extends('layouts.app')
@section('title', $schedule->exists ? 'Edit Jadwal Mengajar' : 'Tambah Pertemuan')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Jadwal Mengajar', 'url' => route('admin.schedules.index')],
                ['label' => $schedule->exists ? 'Edit Pertemuan' : 'Tambah Pertemuan'],
            ]" />
<h1 class="page-title">{{ $schedule->exists ? 'Edit '.$schedule->meetingLabel() : 'Tambah Pertemuan' }}</h1>
            <p class="text-muted small mb-0">
                @if($schedule->exists)
                    Nomor pertemuan tidak berubah walau tanggalnya dipindah — pindah tanggal cukup lewat kolom
                    "Pindah Tanggal" di daftar sesi.
                @else
                    Isi detail pertemuan — sekolah, kelas, program, coach, jam, dan tanggalnya. Tanggal boleh
                    dikosongkan dulu bila belum ditetapkan.
                @endif
            </p>
        </div>
        <a href="{{ route('admin.schedules.index', ['view' => 'sesi']) }}" class="btn btn-light border d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
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

    <form method="POST"
          action="{{ $schedule->exists ? route('admin.schedules.update', $schedule) : route('admin.schedules.store') }}"
          class="card shadow-sm border-0">
        @csrf
        @if($schedule->exists)
            @method('PUT')
        @endif
        <div class="card-body p-4">
            <div class="row g-3">
                {{-- Tanggal TIDAK wajib: pertemuan boleh masuk rencana dulu
                     (mis. "Pertemuan 7") dan tanggalnya ditetapkan belakangan.
                     Tanggal diisi manual — tidak harus berjarak 7 hari. --}}
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tanggal Pertemuan</label>
                    <input type="date" name="session_date" class="form-control"
                           value="{{ old('session_date', $schedule->session_date?->format('Y-m-d')) }}">
                    <div class="form-text">
                        Boleh dikosongkan = "Belum dijadwalkan". Tanggal bebas, tidak harus mingguan.
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status Pertemuan</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Models\TeachingSchedule::STATUS_LABELS as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}"
                                {{ old('status', $schedule->status ?? \App\Models\TeachingSchedule::STATUS_SCHEDULED) === $statusValue ? 'selected' : '' }}>
                                {{ $statusLabel }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        @if($schedule->exists)
                            Status "Inactive" diatur lewat tombol aktif/nonaktif di daftar sesi.
                        @else
                            Terlaksana / Ditunda / Dibatalkan bisa diubah kapan saja.
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sekolah <span class="text-danger">*</span></label>
                    <select name="school_id" id="school_id" class="form-select" required>
                        <option value="">— Pilih Sekolah —</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}"
                                {{ old('school_id', $schedule->school_id) == $school->id ? 'selected' : '' }}>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                    <select name="class_id" id="class_id" class="form-select" required>
                        <option value="">— Pilih Kelas —</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" data-school="{{ $class->school_id }}"
                                {{ old('class_id', $schedule->class_id) == $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">— Tanpa Program —</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}"
                                {{ old('program_id', $schedule->program_id) == $program->id ? 'selected' : '' }}>
                                {{ $program->name }} ({{ $program->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
                    <input type="time" name="start_time" class="form-control" required
                           value="{{ old('start_time', $schedule->start_time?->format('H:i')) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
                    <input type="time" name="end_time" class="form-control" required
                           value="{{ old('end_time', $schedule->end_time?->format('H:i')) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Jumlah Murid</label>
                    <input type="number" name="student_count" class="form-control" min="0" max="255"
                           id="student_count"
                           value="{{ old('student_count', $schedule->student_count) }}">
                    <div class="form-text" id="student_count_hint">Diisi otomatis dari roster kelas.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Jalan Minggu Ini?</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="jalan_minggu_ini" value="1"
                               id="jalanMingguIni"
                               {{ old('jalan_minggu_ini', $schedule->exists ? $schedule->jalan_minggu_ini : true) ? 'checked' : '' }}>
                        <label class="form-check-label small text-muted" for="jalanMingguIni">Sesi berjalan</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Coach Utama <span class="text-danger">*</span></label>
                    <select name="coach_id" id="coach_id" class="form-select" required>
                        <option value="">— Pilih Coach —</option>
                        @foreach($coaches as $coach)
                            <option value="{{ $coach->id }}"
                                {{ old('coach_id', $schedule->coach_id) == $coach->id ? 'selected' : '' }}>
                                {{ $coach->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Coach Tambahan</label>
                    <select name="additional_coaches[]" id="additional_coaches" class="form-select" multiple size="4">
                        @foreach($coaches as $coach)
                            <option value="{{ $coach->id }}"
                                {{ in_array($coach->id, old('additional_coaches', $schedule->additionalCoaches->pluck('id')->all())) ? 'selected' : '' }}>
                                {{ $coach->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Tahan Ctrl / Cmd untuk memilih beberapa coach.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tools DK</label>
                    <input type="text" name="tools_dk" class="form-control" maxlength="255"
                           placeholder="cth. Laptop 12 unit"
                           value="{{ old('tools_dk', $schedule->tools_dk) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tools RK</label>
                    <input type="text" name="tools_rk" class="form-control" maxlength="255"
                           placeholder="cth. Box RK A + B"
                           value="{{ old('tools_rk', $schedule->tools_rk) }}">
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Topik / Materi</label>
                    <input type="text" name="topic" class="form-control" maxlength="255"
                           value="{{ old('topic', $schedule->topic) }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2" maxlength="1000"
                              placeholder="Catatan tambahan untuk sesi ini">{{ old('keterangan', $schedule->keterangan) }}</textarea>
                </div>
            </div>

            <hr class="my-4">

            {{-- Roster murid kelas terpilih (sumber: master students existing) --}}
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-people text-primary"></i>
                <span class="fw-semibold">Murid Kelas Terpilih</span>
                <small class="text-muted">— dari master data siswa; kelola lewat menu Data Siswa</small>
            </div>
            <div id="class-roster" class="mb-2">
                <span class="text-muted small">Pilih sekolah dan kelas untuk melihat murid.</span>
            </div>
            <a id="manage-students-link" href="#" class="btn btn-sm btn-light border d-none mb-2">
                <i class="bi bi-people me-1"></i> Kelola Murid Kelas Ini
            </a>
            @if($classes->isEmpty() || $schools->isEmpty() || $coaches->isEmpty())
            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i>
                Belum ada data master lengkap? Jadwal memakai master data yang sudah ada —
                tambahkan dulu lewat menu master:
                <a href="{{ route('admin.schools.index') }}">Sekolah</a>,
                <a href="{{ route('admin.classes.index') }}">Kelas</a>,
                <a href="{{ route('admin.programs.index') }}">Program</a>,
                <a href="{{ route('admin.coaches.index') }}">Coach</a>.
            </div>
            @endif

            <div class="d-flex align-items-center gap-2 mb-3 mt-4">
                <i class="bi bi-signpost-split text-primary"></i>
                <span class="fw-semibold">Keberangkatan (opsional)</span>
                <small class="text-muted">— untuk coach yang berangkat dari center ke sekolah</small>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lokasi Berangkat</label>
                    <input type="text" name="departure_location" class="form-control" maxlength="100"
                           placeholder="cth. BSD"
                           value="{{ old('departure_location', $schedule->departure_location) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Jam Berangkat</label>
                    <input type="time" name="departure_time" class="form-control"
                           value="{{ old('departure_time', $schedule->departure_time?->format('H:i')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Jam Sampai</label>
                    <input type="time" name="arrival_time" class="form-control"
                           value="{{ old('arrival_time', $schedule->arrival_time?->format('H:i')) }}">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2 border-top-0">
            <a href="{{ route('admin.schedules.index', ['view' => 'sesi']) }}" class="btn btn-light border">Batal</a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i> {{ $schedule->exists ? 'Simpan Perubahan' : 'Simpan Pertemuan' }}
            </button>
        </div>
    </form>
</div>

<script>
    // Filter pilihan kelas mengikuti sekolah yang dipilih, lalu ambil roster
    // murid kelas dari master students (endpoint schedules/class-students).
    document.addEventListener('DOMContentLoaded', function () {
        var schoolSelect = document.getElementById('school_id');
        var classSelect = document.getElementById('class_id');
        var roster = document.getElementById('class-roster');
        var countInput = document.getElementById('student_count');
        var manageLink = document.getElementById('manage-students-link');
        if (!schoolSelect || !classSelect) return;

        function filterClasses() {
            var schoolId = schoolSelect.value;
            Array.from(classSelect.options).forEach(function (option) {
                if (!option.value) return;
                option.hidden = schoolId !== '' && option.dataset.school !== schoolId;
            });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function loadRoster() {
            var classId = classSelect.value;
            if (!classId) {
                roster.innerHTML = '<span class="text-muted small">Pilih sekolah dan kelas untuk melihat murid.</span>';
                manageLink.classList.add('d-none');
                return;
            }

            roster.innerHTML = '<span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span> Memuat murid...</span>';

            fetch('{{ url('admin/schedules/class-students') }}/' + classId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (data) {
                    if (!data.count) {
                        roster.innerHTML = '<span class="text-warning small">Belum ada murid pada kelas ini — tambahkan murid terlebih dahulu agar jumlah murid akurat.</span>';
                    } else {
                        var names = data.students.map(function (s) {
                            return '<span class="badge bg-light text-dark border border-secondary-subtle me-1 mb-1">' + escapeHtml(s.name) + '</span>';
                        }).join('');
                        roster.innerHTML = '<div>' + data.count + ' murid:</div><div class="mt-1">' + names + '</div>';
                        if (countInput && (countInput.value === '' || countInput.value === null)) {
                            countInput.value = data.count;
                        }
                    }
                    manageLink.href = '{{ url('classes') }}/' + classId + '/students';
                    manageLink.classList.remove('d-none');
                })
                .catch(function () {
                    roster.innerHTML = '<span class="text-muted small">Roster murid tidak dapat dimuat.</span>';
                    manageLink.classList.add('d-none');
                });
        }

        schoolSelect.addEventListener('change', function () {
            filterClasses();
            loadRoster();
        });
        classSelect.addEventListener('change', loadRoster);
        filterClasses();
        if (classSelect.value) {
            loadRoster();
        }
    });
</script>
@endsection
