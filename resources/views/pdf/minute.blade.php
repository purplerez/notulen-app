<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Notulen - {{ $meeting->title }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 15mm 15mm 18mm 15mm;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
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

        /* Recorded Notice */
        .recorded-notice {
            font-size: 8pt;
            font-style: italic;
            color: #6b7280;
            text-align: right;
            margin-bottom: 8px;
        }

        /* Document Header */
        .doc-title {
            text-align: center;
            margin-bottom: 14px;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #111827;
        }

        .doc-title .meeting-title {
            margin-top: 4px;
            font-size: 11pt;
            font-weight: bold;
            color: #374151;
        }

        /* Metadata Table */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 9pt;
        }

        .meta-table td {
            padding: 4px 6px;
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

        /* Content Sections */
        .section-block {
            margin-bottom: 14px;
            page-break-inside: auto;
        }

        .section-heading {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            border-bottom: 1.5px solid #93c5fd;
            padding-bottom: 3px;
            margin-bottom: 6px;
            page-break-after: avoid;
        }

        .section-content {
            font-size: 9.5pt;
            text-align: justify;
            color: #1f2937;
        }

        .section-content p {
            margin: 0 0 6px 0;
        }

        /* Lists */
        ol.custom-list, ul.custom-list {
            margin: 4px 0 8px 0;
            padding-left: 20px;
        }

        ol.custom-list li, ul.custom-list li {
            margin-bottom: 4px;
            text-align: justify;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8.5pt;
        }

        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            vertical-align: top;
        }

        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #334155;
            text-align: left;
        }

        .data-table th.center, .data-table td.center {
            text-align: center;
        }

        /* Signature Block */
        .signature-container {
            margin-top: 24px;
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
            font-size: 9.5pt;
            color: #374151;
            margin-bottom: 4px;
        }

        .sig-space {
            height: 55px;
        }

        .sig-name {
            font-size: 9.5pt;
            font-weight: bold;
            text-decoration: underline;
            color: #111827;
        }

        .sig-position {
            font-size: 8.5pt;
            color: #4b5563;
            margin-top: 2px;
        }

        /* Footer & Page numbers */
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
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

    <!-- Label Rapat Direkam -->
    @if($meeting->show_recorded_notice)
        <div class="recorded-notice">
            * Rapat direkam untuk notulensi
        </div>
    @endif

    <!-- Document Title -->
    <div class="doc-title">
        <h1>NOTULEN RAPAT</h1>
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
            <td class="label">Notulis</td>
            <td class="val">{{ $meeting->creator?->name ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Gaya Notulen</td>
            <td class="val">{{ ucfirst($meeting->style) }}</td>
            <td class="label">Status / Versi</td>
            <td class="val">
                Versi {{ $minute->version }}
                @if($minute->isFinal())
                    (Final - {{ $minute->finalized_at?->translatedFormat('d/m/Y H:i') }})
                @else
                    (Draf Belum Difinalkan)
                @endif
            </td>
        </tr>
    </table>

    <!-- 1. Ringkasan Eksekutif -->
    <div class="section-block">
        <div class="section-heading">I. Ringkasan Rapat</div>
        <div class="section-content">
            <p>{{ $minute->getSection('ringkasan') ?: 'Tidak ada ringkasan yang dicatat.' }}</p>
        </div>
    </div>

    <!-- 2. Agenda Rapat (jika ada) -->
    @php $agenda = $minute->getSection('agenda', []); @endphp
    @if(! empty($agenda))
        <div class="section-block">
            <div class="section-heading">II. Agenda Rapat</div>
            <div class="section-content">
                <ol class="custom-list">
                    @foreach($agenda as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    @endif

    <!-- 3. Pembahasan (jika ada) -->
    @php $pembahasan = $minute->getSection('pembahasan', ''); @endphp
    @if(! empty($pembahasan))
        <div class="section-block">
            <div class="section-heading">III. Pembahasan</div>
            <div class="section-content">
                {!! nl2br(e($pembahasan)) !!}
            </div>
        </div>
    @endif

    <!-- 4. Keputusan Rapat -->
    @php $keputusan = $minute->getSection('keputusan', []); @endphp
    <div class="section-block">
        <div class="section-heading">IV. Keputusan Rapat</div>
        <div class="section-content">
            @if(! empty($keputusan))
                <ol class="custom-list">
                    @foreach($keputusan as $k)
                        <li>{{ $k }}</li>
                    @endforeach
                </ol>
            @else
                <p><em>Tidak ada butir keputusan spesifik yang dicatat.</em></p>
            @endif
        </div>
    </div>

    <!-- 5. Matriks Tindak Lanjut (bila use_followup aktif) -->
    @if($meeting->use_followup)
        <div class="section-block">
            <div class="section-heading">V. Tindak Lanjut (Action Items)</div>
            @if($minute->actionItems->isNotEmpty())
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="center" style="width: 25px;">No</th>
                            <th>Uraian Tugas / Tindak Lanjut</th>
                            <th style="width: 120px;">Penanggung Jawab</th>
                            <th class="center" style="width: 85px;">Tenggat</th>
                            <th class="center" style="width: 75px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($minute->actionItems as $idx => $act)
                            <tr>
                                <td class="center">{{ $idx + 1 }}</td>
                                <td>{{ $act->description }}</td>
                                <td>{{ $act->assignee_name ?: '-' }}</td>
                                <td class="center">{{ $act->due_date ? \Carbon\Carbon::parse($act->due_date)->format('d/m/Y') : '-' }}</td>
                                <td class="center">{{ ucfirst(str_replace('_', ' ', $act->status)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="section-content">
                    <p><em>Tidak ada butir tindak lanjut yang ditentukan.</em></p>
                </div>
            @endif
        </div>
    @endif

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

    <!-- Lampiran Daftar Hadir (jika show_attendance aktif) -->
    @if($meeting->show_attendance && $meeting->attendees->isNotEmpty())
        <div style="page-break-before: always; margin-top: 15px;">
            <div class="doc-title" style="margin-bottom: 12px;">
                <h1 style="font-size: 11pt;">LAMPIRAN: DAFTAR HADIR PESERTA</h1>
                <div class="meeting-title" style="font-size: 9.5pt;">{{ $meeting->title }}</div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th class="center" style="width: 30px;">No</th>
                        <th>Nama Peserta</th>
                        <th>Jabatan</th>
                        <th class="center" style="width: 90px;">Status Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($meeting->attendees as $idx => $att)
                        <tr>
                            <td class="center">{{ $idx + 1 }}</td>
                            <td>{{ $att->name }}</td>
                            <td>{{ $att->position ?: '-' }}</td>
                            <td class="center">
                                @if($att->status)
                                    {{ ucfirst(str_replace('_', ' ', $att->status)) }}
                                @else
                                    <em>Belum Absen</em>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</body>
</html>
