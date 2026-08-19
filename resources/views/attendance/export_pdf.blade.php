<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm 12mm 15mm 12mm;
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

        .report-section {
            margin-bottom: 20px;
            page-break-after: always;
        }

        .report-section:last-child {
            page-break-after: avoid;
        }

        .school-header {
            background: #1e3a5f;
            color: #ffffff;
            padding: 10px 14px;
            border-radius: 4px 4px 0 0;
            margin-bottom: 0;
        }

        .school-header h2 {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .school-header p {
            font-size: 8pt;
            opacity: 0.85;
            margin-top: 3px;
        }

        .class-block {
            margin-top: 14px;
        }

        .class-header {
            background: #e8f0fe;
            border-left: 4px solid #1e3a5f;
            padding: 5px 10px;
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        thead tr {
            background: #1e3a5f;
            color: #ffffff;
        }

        thead th {
            padding: 5px 6px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #3a5f8f;
            white-space: nowrap;
        }

        thead th.student-col {
            text-align: left;
            min-width: 110px;
        }

        tbody tr:nth-child(even) {
            background: #f0f4fb;
        }

        tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        tbody td {
            padding: 4px 6px;
            border: 1px solid #d0d8e8;
            text-align: center;
            color: #333;
        }

        tbody td.student-name {
            text-align: left;
            font-weight: 600;
            color: #1a1a2e;
        }

        .status-hadir   { color: #1a7f37; font-weight: bold; }
        .status-absen   { color: #b91c1c; font-weight: bold; }
        .status-sakit   { color: #b45309; font-weight: bold; }
        .status-izin    { color: #1d4ed8; font-weight: bold; }
        .status-empty   { color: #9ca3af; }

        .report-title {
            font-size: 16pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 2px;
        }

        .report-subtitle {
            font-size: 8.5pt;
            color: #555;
            margin-bottom: 14px;
        }

        .footer {
            margin-top: 8px;
            font-size: 7pt;
            color: #9ca3af;
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="report-title">LAPORAN KEHADIRAN SISWA</div>
    <div class="report-subtitle">Dicetak: {{ now()->format('d M Y, H:i') }}</div>

    @forelse($matrix as $schoolName => $classes)
    <div class="report-section">

        <div class="school-header">
            <h2>{{ $schoolName }}</h2>
            <p>Data kehadiran siswa</p>
        </div>

        @foreach($classes as $className => $students)
        <div class="class-block">
            <div class="class-header">Kelas: {{ $className }}</div>

            <table>
                <thead>
                    <tr>
                        <th class="student-col">Nama Siswa</th>
                        @foreach($dates as $date)
                            <th>{{ \Carbon\Carbon::parse($date)->format('d/m') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $studentName => $attendanceDates)
                    <tr>
                        <td class="student-name">{{ $studentName }}</td>
                        @foreach($dates as $date)
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
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        <div class="footer">Learning Report System &mdash; Dicetak: {{ now()->format('d M Y H:i:s') }}</div>
    </div>
    @empty
        <p style="color:#777;text-align:center;margin-top:60px;">Tidak ada data attendance untuk diekspor.</p>
    @endforelse

</body>
</html>
