<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Report – {{ $report->school->name }} – {{ $report->report_date->format('d M Y') }}</title>
    <style>
        /* =====================================================================
           Dokumen cetak A4 untuk laporan coach.

           Ukuran teks memakai satuan pt supaya APA YANG TERLIHAT DI LAYAR
           sama dengan yang keluar di kertas (1pt = 1/72 inci), jadi pembaca
           30–50 tahun tidak perlu zoom. Baseline 11.5pt ± 15px dan
           line-height 1.7 — jauh lebih lega daripada ukuran kecil 9–10px
           yang lazim dipakai untuk "menghemat kertas", tetapi tetap hemat
           karena margin dan spasi diatur, bukan fontnya yang dikecilkan.
           ===================================================================== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        @page { size: A4; margin: 16mm 15mm 18mm; }

        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            font-size: 11.5pt;
            line-height: 1.7;
            color: #1a1a1a;
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }

        /* Lembar A4 untuk pratinjau di browser. Di atas kertas, margin
           dikerjakan oleh @page sehingga padding lembar dinolkan agar tidak
           dobel. */
        .sheet {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 16mm 15mm 18mm;
            background: #fff;
        }

        @media screen {
            body { background: #eef2f7; padding: 20px 12px 40px; }
            .sheet { box-shadow: 0 2px 18px rgba(15, 23, 42, .16); border-radius: 2px; }
        }

        /* ===== Header / Brand ===== */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16pt;
            border-bottom: 2.5pt solid #2563eb;
            padding-bottom: 12pt;
            margin-bottom: 18pt;
            break-inside: avoid;
        }
        .report-header .brand { font-size: 19pt; font-weight: 700; color: #2563eb; line-height: 1.2; }
        .report-header .brand small { display: block; font-size: 10.5pt; font-weight: 400; color: #555; margin-top: 3pt; letter-spacing: .2pt; }
        .report-header .meta { text-align: right; }
        .report-header .meta .badge-approved {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            border: 1pt solid #86efac;
            border-radius: 4pt;
            padding: 3pt 10pt;
            font-size: 10.5pt;
            font-weight: 700;
            letter-spacing: .4pt;
            text-transform: uppercase;
        }
        .report-header .meta .report-id { font-size: 10.5pt; color: #666; margin-top: 5pt; }

        /* ===== Judul bagian =====
           `break-after: avoid` menahan judul selalu menempel pada isinya:
           judul tidak pernah tertinggal sendirian di dasar halaman. */
        .section-title {
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: .6pt;
            text-transform: uppercase;
            color: #1d4ed8;
            border-bottom: 1pt solid #dbeafe;
            padding-bottom: 5pt;
            margin: 20pt 0 10pt;
            break-after: avoid;
            break-inside: avoid;
            page-break-after: avoid;
        }

        /* ===== Info Grid ===== */
        .info-grid {
            display: grid;
            grid-template-columns: 45mm 1fr;
            gap: 7pt 12pt;
            font-size: 11.5pt;
        }
        .info-grid dt { color: #4b5563; font-weight: 600; break-inside: avoid; }
        .info-grid dd { color: #111827; break-inside: avoid; overflow-wrap: break-word; }

        /* ===== Blok teks (materi, goals, activity) =====
           line-height paling lega di dokumen ini karena paragrafnya panjang
           dan paling sering dibaca. Blok boleh terpotong antar halaman
           (materi bisa panjang), tetapi barisnya tidak pernah terpisah
           sendirian: orphans/widows menjaga minimal dua baris. */
        .text-block {
            background: #f8fafc;
            border: 1pt solid #e2e8f0;
            border-radius: 5pt;
            padding: 12pt 14pt;
            white-space: pre-line;
            line-height: 1.75;
            font-size: 11.5pt;
            overflow-wrap: break-word;
            orphans: 2;
            widows: 2;
        }

        /* ===== Accident notes ===== */
        .accident-box {
            background: #fef2f2;
            border: 1pt solid #fca5a5;
            border-left: 4pt solid #ef4444;
            border-radius: 5pt;
            padding: 10pt 14pt;
            margin-bottom: 18pt;
            color: #7f1d1d;
            font-size: 11.5pt;
            break-inside: avoid;
        }
        .accident-box strong { color: #dc2626; }

        /* ===== Tabel ===== */
        table { width: 100%; border-collapse: collapse; margin-top: 4pt; font-size: 11pt; }
        thead th {
            background: #eff6ff;
            color: #1e40af;
            font-size: 11pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3pt;
            padding: 8pt 10pt;
            border-bottom: 1.5pt solid #bfdbfe;
            text-align: left;
        }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tbody td { padding: 7pt 10pt; border-bottom: 1pt solid #e5e7eb; }
        .badge {
            display: inline-block;
            border-radius: 4pt;
            padding: 2pt 9pt;
            font-size: 10.5pt;
            font-weight: 700;
        }
        .badge-present  { background: #dcfce7; color: #166534; }
        .badge-absent   { background: #fee2e2; color: #991b1b; }
        .badge-sick     { background: #fef9c3; color: #854d0e; }
        .badge-permission { background: #e0f2fe; color: #075985; }

        /* ===== Galeri media =====
           Satu foto per baris, selebar area konten. Foto kecil dalam grid
           dua kolom memaksa pembaca men-zoom; ukuran penuh membuat isi foto
           langsung terbaca tanpa memperbesar apa pun. `height: auto` menjaga
           rasio asli — foto tidak pernah di-stretch. */
        .media-list { margin-top: 4pt; }

        .media-figure {
            margin: 0 0 18pt;
            border: 1pt solid #e2e8f0;
            border-radius: 5pt;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .media-figure img {
            display: block;
            width: 100%;
            height: auto;
            max-width: 100%;
        }

        .media-caption {
            font-size: 10.5pt;
            color: #4b5563;
            padding: 6pt 10pt;
            background: #f8fafc;
            border-top: 1pt solid #e2e8f0;
            /* "break-word", bukan "break-all": nama file panjang tetap
               terpotong, tetapi angka pendek ("10", "2026") tidak pernah
               dipecah antar digit. */
            overflow-wrap: break-word;
            word-break: normal;
            line-height: 1.5;
        }

        /* ===== Blok dokumentasi video =====
           Video tidak bisa diputar di dalam dokumen cetak, jadi blok ini
           adalah pengganti yang jelas: area penuh dengan tautan berlabel
           menuju halaman detail laporan, nama berkasnya, dan URL cadangan
           berukuran terbaca untuk salinan kertas. */
        .video-block {
            display: block;
            border: 1pt solid #cbd5e1;
            border-radius: 5pt;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 18pt;
        }
        .video-block .video-head {
            display: flex;
            align-items: center;
            gap: 12pt;
            padding: 14pt 16pt;
            background: #1e293b;
            color: #f1f5f9;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .video-block .play-icon {
            width: 34pt;
            height: 34pt;
            border-radius: 50%;
            background: #2563eb;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15pt;
            flex: 0 0 auto;
        }
        .video-block .video-title {
            font-size: 13pt;
            font-weight: 700;
            letter-spacing: .3pt;
            color: #fff;
            line-height: 1.35;
        }
        .video-block .video-file {
            font-size: 10.5pt;
            color: #cbd5e1;
            margin-top: 2pt;
            overflow-wrap: break-word;
        }
        .video-block .video-body {
            padding: 10pt 16pt 12pt;
            background: #f8fafc;
            border-top: 1pt solid #e2e8f0;
        }
        .video-block .video-hint { font-size: 10.5pt; color: #4b5563; }
        /* URL cadangan untuk salinan kertas — ukurannya dibaca mata normal,
           dan boleh terpotong di mana saja supaya tidak melebar keluar area
           konten A4. */
        .video-block .video-url {
            display: block;
            margin-top: 4pt;
            font-size: 10.5pt;
            color: #1d4ed8;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.5;
        }

        /* ===== Approval stamp ===== */
        .approval-row {
            display: flex;
            gap: 24pt;
            margin-top: 26pt;
            border-top: 1pt solid #e5e7eb;
            padding-top: 16pt;
            break-inside: avoid;
        }
        .approval-box {
            flex: 1;
            border: 1pt solid #e5e7eb;
            border-radius: 5pt;
            padding: 12pt;
            text-align: center;
        }
        .approval-box .label { font-size: 10.5pt; color: #4b5563; margin-bottom: 34pt; }
        .approval-box .sign-line { border-top: 1pt solid #1a1a1a; margin: 0 20pt 6pt; }
        .approval-box .name { font-size: 11pt; font-weight: 700; }

        /* ===== Footer ===== */
        .report-footer {
            margin-top: 20pt;
            font-size: 9.5pt;
            color: #6b7280;
            text-align: center;
            border-top: 1pt solid #e5e7eb;
            padding-top: 9pt;
            line-height: 1.5;
            break-inside: avoid;
        }

        /* ===== Print ===== */
        @media print {
            body { background: #fff; font-size: 11.5pt; }
            .sheet { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
            a { text-decoration: none; color: inherit; }
            .video-block .video-url { color: #1d4ed8; }
        }
    </style>
</head>
<body>

{{-- Aksi layar (tidak ikut tercetak). --}}
<div class="no-print" style="max-width: 210mm; margin: 0 auto 14px; text-align: right;">
    @php
        $backUrl = match ($backTo ?? null) {
            'pic'      => route('pic.dashboard'),
            'coach'    => route('coach.reports.index'),
            'admin'    => route('admin.reports.index'),
            default    => url()->previous() !== url()->current() ? url()->previous() : route('admin.reports.index'),
        };

        // Tautan pada blok video mengarah ke HALAMAN DETAIL laporan — bukan ke
        // URL file video langsung. Halaman detail itu sendiri yang memutar
        // video lewat route media terotorisasi (media.serve), sehingga file
        // privat tidak pernah diekspos dan siapa pun yang membuka tautan ini
        // tetap melewati pemeriksaan akses yang sama dengan halaman lain.
        $detailUrl = match ($backTo ?? null) {
            'pic'   => route('pic.reports.show', $report),
            'coach' => route('coach.reports.show', $report),
            default => route('admin.reports.show', $report),
        };
    @endphp
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:10px 22px;font-size:14px;cursor:pointer;font-weight:600;">
        🖨 Cetak / Simpan PDF
    </button>
    <a href="{{ $backUrl }}" style="margin-left:8px;background:#fff;color:#374151;border:1px solid #cbd5e1;border-radius:6px;padding:10px 22px;font-size:14px;text-decoration:none;font-weight:600;display:inline-block;">
        ← Kembali
    </a>
</div>

<div class="sheet">

    {{-- ===== Header ===== --}}
    <header class="report-header">
        <div class="brand">
            DigiKidz
            <small>Coach Report</small>
        </div>
        <div class="meta">
            <span class="badge-approved">✔ Disetujui</span>
            <div class="report-id">ID Laporan #{{ $report->id }}</div>
        </div>
    </header>

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
                <th style="width:30pt;">#</th>
                <th>Nama Siswa</th>
                <th style="width:120pt;">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $attLabels = ['present'=>'Hadir','absent'=>'Absen','sick'=>'Sakit','permission'=>'Izin'];
                $attClass  = ['present'=>'badge-present','absent'=>'badge-absent','sick'=>'badge-sick','permission'=>'badge-permission'];
            @endphp
            @foreach($report->attendances as $i => $att)
            <tr>
                <td style="color:#6b7280;">{{ $i + 1 }}</td>
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
         di-embed playable pada PDF — dirender sebagai blok dokumentasi
         bertaut ke halaman detail laporan. --}}
    @php
        $photos = $report->media->where('type', 'photo')->values();
        $videos = $report->media->where('type', 'video')->values();
        $attendance = $report->media->where('type', 'attendance')->values();
    @endphp

    @if($photos->count() > 0)
    <div class="section-title">Foto Kegiatan ({{ $photos->count() }})</div>
    <div class="media-list">
        @foreach($photos as $photo)
        <figure class="media-figure">
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
    <div class="media-list">
        @foreach($attendance as $media)
        <figure class="media-figure">
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
    <div class="media-list">
        @foreach($videos as $video)
        {{-- Video tidak bisa di-embed playable di dokumen cetak, jadi blok
             ini menggantikannya: label tautan yang bisa diklik menuju halaman
             detail laporan, plus URL cadangan untuk salinan kertas. --}}
        <a class="video-block" href="{{ $detailUrl }}" target="_blank" rel="noopener">
            <span class="video-head">
                <span class="play-icon">▶</span>
                <span>
                    <span class="video-title">Lihat Video</span>
                    <span class="video-file">{{ $video->original_name ?? basename($video->path) }}</span>
                </span>
            </span>
            <span class="video-body">
                <span class="video-hint">Video dapat diputar dan diunduh dari halaman detail laporan:</span>
                <span class="video-url">{{ $detailUrl }}</span>
            </span>
        </a>
        @endforeach
    </div>
    @endif

    @if($photos->isEmpty() && $videos->isEmpty() && $attendance->isEmpty())
    <div class="section-title">Media Terlampir</div>
    <p style="color:#6b7280;">Tidak ada media terlampir pada laporan ini.</p>
    @endif

    {{-- ===== Footer ===== --}}
    <div class="report-footer">
        Dicetak melalui DigiKidz Learning Report System &bull; {{ now()->format('d F Y, H:i') }} &bull; Dokumen ini sah tanpa tanda tangan basah.
    </div>

</div>

</body>
</html>
