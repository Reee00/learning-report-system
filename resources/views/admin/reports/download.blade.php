<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Report – {{ $report->school->name }} – {{ $report->report_date->format('d M Y') }}</title>
    <style>
        /* ===== Reset & Base ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
            padding: 32px 40px;
            max-width: 820px;
            margin: 0 auto;
        }

        /* ===== Header / Brand ===== */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .report-header .brand { font-size: 20px; font-weight: 700; color: #2563eb; }
        .report-header .brand small { display: block; font-size: 11px; font-weight: 400; color: #666; margin-top: 2px; }
        .report-header .meta { text-align: right; }
        .report-header .meta .badge-approved {
            display: inline-block;
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
            border-radius: 4px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .report-header .meta .report-id { font-size: 11px; color: #999; margin-top: 4px; }

        /* ===== Section title ===== */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: #2563eb;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 6px;
            margin: 20px 0 10px;
        }

        /* ===== Info Grid ===== */
        .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 6px 12px; }
        .info-grid dt { color: #666; font-weight: 500; }
        .info-grid dd { color: #1a1a1a; }

        /* ===== Text block ===== */
        .text-block {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            white-space: pre-line;
            line-height: 1.6;
        }

        /* ===== Accident notes ===== */
        .accident-box {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 20px;
            color: #7f1d1d;
        }
        .accident-box strong { color: #ef4444; }

        /* ===== Attendance table ===== */
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        thead th {
            background: #eff6ff;
            color: #1e40af;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 8px 10px;
            border-bottom: 2px solid #bfdbfe;
            text-align: left;
        }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
        .badge {
            display: inline-block;
            border-radius: 4px;
            padding: 1px 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-present  { background: #dcfce7; color: #166534; }
        .badge-absent   { background: #fee2e2; color: #991b1b; }
        .badge-sick     { background: #fef9c3; color: #854d0e; }
        .badge-permission { background: #e0f2fe; color: #075985; }

        /* ===== Media gallery ===== */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 8px;
        }
        .media-item {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .media-item img {
            display: block;
            width: 100%;
            height: auto;
        }
        .media-caption {
            font-size: 10px;
            color: #555;
            padding: 5px 8px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            /* "break-word", bukan "break-all": nama file panjang tetap
               terpotong, tetapi angka pendek ("10", "2026") tidak pernah
               dipecah antar digit. */
            overflow-wrap: break-word;
            word-break: normal;
        }
        /* Placeholder poster untuk video — tidak bisa di-embed playable di PDF. */
        .video-poster {
            position: relative;
            aspect-ratio: 16 / 9;
            background: #1e293b;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
        }
        .video-poster .play-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #2563eb;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 6px;
        }
        .video-poster .video-label { font-size: 10px; letter-spacing: .5px; text-transform: uppercase; }

        /* ===== Approval stamp ===== */
        .approval-row {
            display: flex;
            gap: 24px;
            margin-top: 30px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .approval-box {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 14px;
            text-align: center;
        }
        .approval-box .label { font-size: 11px; color: #666; margin-bottom: 40px; }
        .approval-box .sign-line { border-top: 1px solid #1a1a1a; margin: 0 20px 6px; }
        .approval-box .name { font-size: 11px; font-weight: 600; }

        /* ===== Footer ===== */
        .report-footer {
            margin-top: 24px;
            font-size: 10px;
            color: #999;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }

        /* ===== Print media ===== */
        @media print {
            body { padding: 12px 16px; }
            .no-print { display: none !important; }
            a { text-decoration: none; color: inherit; }
            .media-item { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

{{-- ===== Print action (hidden on print) ===== --}}
<div class="no-print" style="text-align:right; margin-bottom:16px;">
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:13px;cursor:pointer;font-weight:600;">
        🖨 Cetak / Simpan PDF
    </button>
    {{-- Kembali: kembali ke daftar laporan yang sesuai konteks pengirim,
         bukan history.back() yang bisa keluar dari alur laporan. --}}
    @php
        $backUrl = match ($backTo ?? null) {
            'pic'      => route('pic.dashboard'),
            'coach'    => route('coach.reports.index'),
            'admin'    => route('admin.reports.index'),
            default    => url()->previous() !== url()->current() ? url()->previous() : route('admin.reports.index'),
        };
    @endphp
    <a href="{{ $backUrl }}" style="margin-left:8px;background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;border-radius:6px;padding:8px 20px;font-size:13px;text-decoration:none;font-weight:600;display:inline-block;">
        ← Kembali
    </a>
</div>

{{-- ===== Header ===== --}}
<div class="report-header">
    <div class="brand">
        DigiKidz
        <small>Coach Report</small>
    </div>
    <div class="meta">
        <span class="badge-approved">✔ Disetujui</span>
        <div class="report-id">ID Laporan #{{ $report->id }}</div>
    </div>
</div>

{{-- ===== Accident notes ===== --}}
@if($report->notes)
<div class="accident-box">
    <strong>⚠ Catatan Kecelakaan / Accident Notes:</strong><br>
    {{ $report->notes }}
</div>
@endif

{{-- ===== Informasi Utama ===== --}}
<div class="section-title">Informasi Laporan</div>
<dl class="info-grid">
    <dt>Sekolah</dt>
    <dd>{{ $report->school->name }}</dd>
    <dt>Kelas</dt>
    <dd>{{ $report->schoolClass->name }}</dd>
    <dt>Coach</dt>
    <dd>{{ $report->coach->name }}</dd>
    <dt>Tanggal Pembelajaran</dt>
    <dd>{{ $report->report_date->format('d F Y') }}</dd>
    <dt>Disetujui Pada</dt>
    <dd>{{ $report->approved_at ? $report->approved_at->format('d F Y, H:i') : '-' }}</dd>
</dl>

{{-- ===== Materi ===== --}}
<div class="section-title">Materi Pelajaran</div>
<div class="text-block">{{ $report->lesson_material }}</div>

{{-- ===== Goals Materi (Meeting 2026-09 req. F) ===== --}}
@if($report->goals_materi)
<div class="section-title">Goals Materi</div>
<div class="text-block">{{ $report->goals_materi }}</div>
@endif

{{-- ===== Activity Report (Meeting 2026-09 req. F) ===== --}}
<div class="section-title">Activity Report</div>
<div class="text-block">{{ $report->activity_report }}</div>

<!-- {{-- ===== Absensi ===== --}}
@if($report->attendances->count() > 0)
<div class="section-title">Absensi Siswa ({{ $report->attendances->count() }} siswa)</div>
<table>
    <thead>
        <tr>
            <th style="width:30px;">#</th>
            <th>Nama Siswa</th>
            <th style="width:120px;">Status</th>
        </tr>
    </thead>
    <tbody>
        @php
            $attLabels = ['present'=>'Hadir','absent'=>'Absen','sick'=>'Sakit','permission'=>'Izin'];
            $attClass  = ['present'=>'badge-present','absent'=>'badge-absent','sick'=>'badge-sick','permission'=>'badge-permission'];
        @endphp
        @foreach($report->attendances as $i => $att)
        <tr>
            <td style="color:#999;">{{ $i + 1 }}</td>
            <td>{{ $att->student->name }}</td>
            <td>
                <span class="badge {{ $attClass[$att->status] ?? '' }}">
                    {{ $attLabels[$att->status] ?? $att->status }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif -->

{{-- ===== Media terlampir =====
     Foto & bukti absensi dirender inline (dilayani lewat route
     /media/{id} yang meng-autorisasi per laporan). Video tidak bisa
     di-embed playable pada PDF — dirender sebagai poster + referensi file. --}}
@php
    $photos = $report->media->where('type', 'photo')->values();
    $videos = $report->media->where('type', 'video')->values();
    $attendance = $report->media->where('type', 'attendance')->values();
@endphp

@if($photos->count() > 0)
<div class="section-title">Foto Kegiatan ({{ $photos->count() }})</div>
<div class="media-grid">
    @foreach($photos as $photo)
    <figure class="media-item">
        <img src="{{ $photo->url() }}" alt="{{ $photo->original_name ?? 'Foto kegiatan' }}">
        @if($photo->original_name)
        <figcaption class="media-caption">{{ $photo->original_name }}</figcaption>
        @endif
    </figure>
    @endforeach
</div>
@endif

@if($attendance->count() > 0)
<div class="section-title">Bukti Absensi ({{ $attendance->count() }})</div>
<div class="media-grid">
    @foreach($attendance as $media)
    <figure class="media-item">
        <img src="{{ $media->url() }}" alt="{{ $media->original_name ?? 'Bukti absensi' }}">
        @if($media->original_name)
        <figcaption class="media-caption">{{ $media->original_name }}</figcaption>
        @endif
    </figure>
    @endforeach
</div>
@endif

@if($videos->count() > 0)
<div class="section-title">Video Kegiatan ({{ $videos->count() }})</div>
<div class="media-grid">
    @foreach($videos as $video)
    <figure class="media-item">
        <div class="video-poster">
            <div class="play-icon">▶</div>
            <div class="video-label">File Video</div>
        </div>
        <figcaption class="media-caption">
            🎬 {{ $video->original_name ?? basename($video->path) }}
            — video tersimpan pada sistem, tidak dapat diputar di dokumen cetak.
        </figcaption>
    </figure>
    @endforeach
</div>
@endif

@if($photos->isEmpty() && $videos->isEmpty() && $attendance->isEmpty())
<div class="section-title">Media Terlampir</div>
<p style="color:#999;">Tidak ada media terlampir pada laporan ini.</p>
@endif

{{-- ===== Approval stamp ===== --}}
<!-- <div class="approval-row">
    <div class="approval-box">
        <div class="label">Coach</div>
        <div class="sign-line"></div>
        <div class="name">{{ $report->coach->name }}</div>
    </div>
    <div class="approval-box">
        <div class="label">Disetujui oleh Relation</div>
        <div class="sign-line"></div>
        <div class="name">{{ $report->approvedBy?->name ?? '-' }}</div>
    </div> -->
</div>

{{-- ===== Footer ===== --}}
<div class="report-footer">
    Dicetak melalui DigiKidz Learning Report System &bull; {{ now()->format('d F Y, H:i') }} &bull; Dokumen ini sah tanpa tanda tangan basah.
</div>

</body>
</html>
