<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran Siswa</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 12mm 16mm 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            color: #1a1a2e;
            background: #ffffff;
        }

        /* ===== Kop dokumen ===== */
        .doc-header {
            border-bottom: 3px solid #1e3a5f;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .doc-header .company {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 1px;
        }
        .doc-header .doc-title {
            font-size: 15pt;
            font-weight: bold;
            color: #1a1a2e;
            margin-top: 2px;
        }
        .doc-header .doc-subtitle {
            font-size: 8pt;
            color: #666;
            margin-top: 2px;
        }

        /* ===== Bagian per kelas ===== */
        .class-section {
            page-break-after: always;
        }
        .class-section:last-child {
            page-break-after: avoid;
        }

        .class-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: #f4f7fb;
            border: 1px solid #d0d8e8;
        }
        .class-meta td {
            padding: 4px 10px;
            font-size: 9pt;
            border: 1px solid #d0d8e8;
        }
        .class-meta td.label {
            width: 12%;
            font-weight: bold;
            color: #1e3a5f;
            background: #e8f0fe;
        }

        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a5f;
            margin: 12px 0 5px 0;
        }

        .summary-chips {
            margin-bottom: 10px;
        }
        .summary-chips span {
            display: inline-block;
            border: 1px solid #d0d8e8;
            border-radius: 3px;
            padding: 2px 8px;
            margin-right: 6px;
            font-size: 8.5pt;
        }
        .chip-hadir  { background: #e6f4ea; color: #1a7f37; }
        .chip-absen  { background: #fdecea; color: #b91c1c; }
        .chip-sakit  { background: #fff4e5; color: #b45309; }
        .chip-izin   { background: #e8f0fe; color: #1d4ed8; }

        table.matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        table.matrix thead tr {
            background: #1e3a5f;
            color: #ffffff;
        }

        /* dompdf mengulang thead otomatis pada pergantian halaman. */
        table.matrix thead th {
            padding: 5px 6px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #3a5f8f;
            white-space: nowrap;
        }

        table.matrix thead th.student-col {
            text-align: left;
            min-width: 110px;
        }

        table.matrix thead th.total-col {
            background: #3a5f8f;
            white-space: nowrap;
        }

        table.matrix tbody tr:nth-child(even) {
            background: #f0f4fb;
        }

        table.matrix tbody td {
            padding: 4px 6px;
            border: 1px solid #d0d8e8;
            text-align: center;
            color: #333;
        }

        table.matrix tbody td.student-name {
            text-align: left;
            font-weight: 600;
            color: #1a1a2e;
        }

        table.matrix tbody td.total-col {
            font-weight: bold;
            color: #1e3a5f;
            background: #e8f0fe;
        }

        .status-hadir   { color: #1a7f37; font-weight: bold; }
        .status-absen   { color: #b91c1c; font-weight: bold; }
        .status-sakit   { color: #b45309; font-weight: bold; }
        .status-izin    { color: #1d4ed8; font-weight: bold; }
        .status-empty   { color: #9ca3af; }

        /* Referensi bukti absensi: NAMA FILE saja — media (foto/video)
           tidak pernah di-embed ke PDF. */
        ul.media-refs {
            list-style: none;
            font-size: 8pt;
            color: #444;
        }
        ul.media-refs li {
            padding: 1px 0;
        }

        .footer-note {
            margin-top: 10px;
            font-size: 7.5pt;
            color: #9ca3af;
            text-align: right;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }

        .empty-state {
            text-align: center;
            color: #777;
            margin-top: 80px;
            font-size: 11pt;
        }
    </style>
</head>
<body>

    <div class="doc-header">
        <div class="company">LEARNING REPORT SYSTEM</div>
        <div class="doc-title">LAPORAN KEHADIRAN SISWA</div>
        <div class="doc-subtitle">Dicetak: {{ now()->translatedFormat('d M Y, H:i') }}</div>
    </div>

    @forelse($blocks as $block)
    <div class="class-section">

        {{-- Metadata kelas --}}
        <table class="class-meta">
            <tr>
                <td class="label">Sekolah</td><td>{{ $block['school'] }}</td>
                <td class="label">Kelas</td><td>{{ $block['class'] }}</td>
            </tr>
            <tr>
                <td class="label">Program</td><td>{{ $block['programs'] ?: '-' }}</td>
                <td class="label">Coach</td><td>{{ $block['coaches'] ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Periode</td><td colspan="3">{{ $block['period'] }} &bull; {{ count($block['dates']) }} sesi</td>
            </tr>
        </table>

        {{-- Ringkasan kehadiran kelas --}}
        <div class="summary-chips">
            <span class="chip-hadir">Hadir: {{ $block['summary']['Hadir'] }}</span>
            <span class="chip-absen">Absen: {{ $block['summary']['Absen'] }}</span>
            <span class="chip-sakit">Sakit: {{ $block['summary']['Sakit'] }}</span>
            <span class="chip-izin">Izin: {{ $block['summary']['Izin'] }}</span>
        </div>

        {{-- Matriks kehadiran --}}
        <div class="section-title">Matriks Kehadiran</div>
        <table class="matrix">
            <thead>
                <tr>
                    <th>No</th>
                    <th class="student-col">Nama Siswa</th>
                    @foreach($block['dates'] as $date)
                        <th>{{ \Carbon\Carbon::parse($date)->translatedFormat('d M') }}</th>
                    @endforeach
                    <th class="total-col">TOTAL HADIR</th>
                </tr>
            </thead>
            <tbody>
                @php $number = 0; @endphp
                @foreach($block['students'] as $student => $attendanceDates)
                <tr>
                    <td>{{ ++$number }}</td>
                    <td class="student-name">{{ $student }}</td>
                    @foreach($block['dates'] as $date)
                        @php
                            $val = $attendanceDates[$date] ?? '-';
                            $cssClass = match($val) {
                                'Hadir'  => 'status-hadir',
                                'Absen'  => 'status-absen',
                                'Sakit'  => 'status-sakit',
                                'Izin'   => 'status-izin',
                                default  => 'status-empty',
                            };
                        @endphp
                        <td class="{{ $cssClass }}">{{ $val }}</td>
                    @endforeach
                    <td class="total-col">{{ $block['totals'][$student] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Referensi bukti absensi (nama file, tanpa embed media) --}}
        @if($block['media'] !== [])
        <div class="section-title">Referensi Bukti Absensi</div>
        <ul class="media-refs">
            @foreach($block['media'] as $ref)
                <li>{{ \Carbon\Carbon::parse($ref['date'])->translatedFormat('d M Y') }} &mdash; {{ $ref['name'] }}</li>
            @endforeach
        </ul>
        @endif

        <div class="footer-note">Learning Report System &mdash; {{ $block['school'] }} / {{ $block['class'] }}</div>
    </div>
    @empty
        <div class="empty-state">Tidak ada data kehadiran untuk diekspor.</div>
    @endforelse

    {{-- Nomor halaman footer (dompdf inline PHP) --}}
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $pdf->page_text(770, 570, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 8, [156, 163, 175]);
        }
    </script>

</body>
</html>
