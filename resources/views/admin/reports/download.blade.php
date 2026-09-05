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
        }
    </style>
</head>
<body>

{{-- ===== Print action (hidden on print) ===== --}}
<div class="no-print" style="text-align:right; margin-bottom:16px;">
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:13px;cursor:pointer;font-weight:600;">
        🖨 Cetak / Simpan PDF
    </button>
    <button onclick="window.history.back()" style="margin-left:8px;background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;border-radius:6px;padding:8px 20px;font-size:13px;cursor:pointer;font-weight:600;">
        ← Kembali
    </button>
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

{{-- ===== Ringkasan Kegiatan ===== --}}
<div class="section-title">Ringkasan Kegiatan</div>
<div class="text-block">{{ $report->activity_summary }}</div>

{{-- ===== Absensi ===== --}}
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
@endif

{{-- ===== Media info (not rendered inline for security, listed by filename) ===== --}}
@php
    $photos = $report->media->where('type', 'photo');
    $videos = $report->media->where('type', 'video');
@endphp
@if($photos->count() > 0 || $videos->count() > 0)
<div class="section-title">Media Terlampir</div>
<dl class="info-grid">
    @if($photos->count() > 0)
    <dt>Foto ({{ $photos->count() }})</dt>
    <dd>{{ $photos->pluck('original_name')->filter()->implode(', ') ?: $photos->count() . ' file foto' }}</dd>
    @endif
    @if($videos->count() > 0)
    <dt>Video ({{ $videos->count() }})</dt>
    <dd>{{ $videos->pluck('original_name')->filter()->implode(', ') ?: $videos->count() . ' file video' }}</dd>
    @endif
</dl>
@endif

{{-- ===== Approval stamp ===== --}}
<div class="approval-row">
    <div class="approval-box">
        <div class="label">Coach</div>
        <div class="sign-line"></div>
        <div class="name">{{ $report->coach->name }}</div>
    </div>
    <div class="approval-box">
        <div class="label">Disetujui oleh Relation</div>
        <div class="sign-line"></div>
        <div class="name">{{ $report->approvedBy?->name ?? '-' }}</div>
    </div>
</div>

{{-- ===== Footer ===== --}}
<div class="report-footer">
    Dicetak melalui DigiKidz Learning Report System &bull; {{ now()->format('d F Y, H:i') }} &bull; Dokumen ini sah tanpa tanda tangan basah.
</div>

</body>
</html>
