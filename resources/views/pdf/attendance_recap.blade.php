<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi - {{ $meeting->title }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 15mm 15mm 18mm 15mm;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
            color: #1a202c;
            margin: 0;
            padding: 0;
        }

        /* Kop Surat */
        .kop-container {
            margin-bottom: 12px;
            text-align: center;
        }

        .kop-image {
            max-width: 100%;
            max-height: 110px;
            margin-bottom: 6px;
        }

        .kop-text h2 {
            margin: 0 0 4px 0;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #111827;
        }

        .kop-text p {
            margin: 0;
            font-size: 8.5pt;
            color: #4b5563;
        }

        .kop-divider {
            margin-top: 8px;
            margin-bottom: 12px;
            border-bottom: 3px double #111827;
        }

        /* Title */
        .doc-title {
            text-align: center;
            margin-bottom: 12px;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 12.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #111827;
        }

        .doc-title .sub-title {
            margin-top: 2px;
            font-size: 9pt;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-title .meeting-title {
            margin-top: 3px;
            font-size: 10.5pt;
            font-weight: bold;
            color: #1f2937;
        }

        /* Meta Table */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }

        .meta-table td {
            padding: 3px 5px;
            vertical-align: top;
            border: 1px solid #e5e7eb;
        }

        .meta-table td.label {
            width: 18%;
            background-color: #f9fafb;
            font-weight: bold;
            color: #4b5563;
        }

        .meta-table td.val {
            width: 32%;
            color: #111827;
        }

        /* Summary Box */
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8pt;
            background-color: #f8fafc;
        }

        .summary-box th, .summary-box td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: center;
        }

        .summary-box th {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .summary-box td {
            font-weight: bold;
            color: #0f172a;
        }

        /* Attendee Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 14px;
        }

        .data-table th, .data-table td {
            border: 1px solid #94a3b8;
            padding: 4px 6px;
            vertical-align: middle;
        }

        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
        }

        .data-table th.center, .data-table td.center {
            text-align: center;
        }

        .paraf-cell {
            height: 32px;
            text-align: center;
            vertical-align: bottom;
            font-size: 7pt;
            color: #cbd5e1;
        }

        /* Signature Block */
        .signature-container {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        .signature-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .sig-role {
            font-size: 9pt;
            color: #374151;
            margin-bottom: 4px;
        }

        .sig-space {
            height: 50px;
        }

        .sig-name {
            font-size: 9pt;
            font-weight: bold;
            text-decoration: underline;
            color: #111827;
        }

        .sig-position {
            font-size: 8pt;
            color: #4b5563;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <!-- Kop Surat Organisasi -->
    @if($meeting->use_kop)
        <div class="kop-container">
            @if($kopBase64)
                <img src="{{ $kopBase64 }}" alt="Kop Surat" class="kop-image" />
                <div class="kop-divider"></div>
            @else
                <div class="kop-text">
                    <h2>{{ $organization->name }}</h2>
                    @if($organization->address_text)
                        <p>{{ $organization->address_text }}</p>
                    @endif
                </div>
                <div class="kop-divider"></div>
            @endif
        </div>
    @endif

    <!-- Document Title -->
    <div class="doc-title">
        <h1>DAFTAR HADIR PESERTA RAPAT</h1>
        <div class="sub-title">Lampiran Berita Acara Rapat</div>
        <div class="meeting-title">{{ $meeting->title }}</div>
    </div>

    <!-- Metadata Rapat -->
    <table class="meta-table">
        <tr>
            <td class="label">Hari, Tanggal</td>
            <td class="val">{{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</td>
            <td class="label">Waktu</td>
            <td class="val">{{ $meeting->start_time ? substr($meeting->start_time, 0, 5) . ' WIB' : 'Sesuai Jadwal' }}</td>
        </tr>
        <tr>
            <td class="label">Tempat</td>
            <td class="val">{{ $meeting->location ?: 'Ruang Rapat' }}</td>
            <td class="label">Status Presensi</td>
            <td class="val">
                @if($meeting->absensi_closed_at)
                    Ditutup ({{ \Carbon\Carbon::parse($meeting->absensi_closed_at)->translatedFormat('d/m/Y H:i') }})
                @else
                    Masih Terbuka
                @endif
            </td>
        </tr>
    </table>

    <!-- Ringkasan Jumlah per Status Kehadiran -->
    <table class="summary-box">
        <thead>
            <tr>
                <th>Total Undangan</th>
                <th>Hadir</th>
                <th>Terlambat</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Dinas Luar</th>
                <th>Tanpa Ket.</th>
                <th>% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary['total'] }}</td>
                <td>{{ $summary['hadir'] }}</td>
                <td>{{ $summary['terlambat'] }}</td>
                <td>{{ $summary['izin'] }}</td>
                <td>{{ $summary['sakit'] }}</td>
                <td>{{ $summary['dinas_luar'] }}</td>
                <td>{{ $summary['tanpa_keterangan'] }}</td>
                <td>{{ $attendanceRate }}%</td>
            </tr>
        </tbody>
    </table>

    <!-- Tabel Daftar Hadir & Kolom Paraf -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="center" style="width: 25px;">No</th>
                <th>Nama Lengkap</th>
                <th style="width: 120px;">Jabatan</th>
                <th class="center" style="width: 75px;">Status</th>
                <th class="center" style="width: 65px;">Jam Hadir</th>
                <th class="center" style="width: 55px;">Metode</th>
                <th class="center" style="width: 65px;">Paraf</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendees as $idx => $att)
                <tr>
                    <td class="center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $att->name }}</strong>
                        @if($att->is_guest)
                            <span style="font-size: 7.5pt; color: #64748b;">(Tamu)</span>
                        @endif
                    </td>
                    <td>{{ $att->position ?: '-' }}</td>
                    <td class="center">
                        @if($att->status === 'hadir')
                            Hadir
                        @elseif($att->status === 'terlambat')
                            Terlambat
                        @elseif($att->status === 'izin')
                            Izin
                        @elseif($att->status === 'sakit')
                            Sakit
                        @elseif($att->status === 'dinas_luar')
                            Dinas Luar
                        @elseif($att->status === 'tanpa_keterangan')
                            Alpa
                        @else
                            -
                        @endif
                    </td>
                    <td class="center">
                        {{ $att->checked_in_at ? \Carbon\Carbon::parse($att->checked_in_at)->format('H:i') . ' WIB' : '-' }}
                    </td>
                    <td class="center">
                        @if($att->method === 'qr')
                            QR
                        @elseif($att->method === 'manual')
                            Manual
                        @else
                            -
                        @endif
                    </td>
                    <td class="paraf-cell">
                        <span style="float: left; font-size: 6.5pt; color: #94a3b8;">{{ $idx + 1 }}.</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center" style="padding: 15px; color: #64748b;">
                        <em>Tidak ada peserta terdaftar untuk rapat ini.</em>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Blok Tanda Tangan (jika use_signature aktif) -->
    @if($meeting->use_signature && $signatories->isNotEmpty())
        <div class="signature-container">
            <table class="signature-table">
                <tr>
                    @foreach($signatories as $sig)
                        <td style="width: {{ round(100 / max(1, $signatories->count()), 1) }}%;">
                            <div class="sig-role">{{ $sig->role_label ?: 'Mengetahui,' }}</div>
                            <div class="sig-space"></div>
                            <div class="sig-name">{{ $sig->name }}</div>
                            <div class="sig-position">{{ $sig->position ?: '-' }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

</body>
</html>
