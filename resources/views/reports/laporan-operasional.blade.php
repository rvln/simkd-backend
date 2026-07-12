<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Operasional SIMDK</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1a1a2e;
            background: #ffffff;
        }

        /* ── Header ── */
        .report-header {
            background-color: #0d9488;
            color: #ffffff;
            padding: 28px 32px;
            margin-bottom: 24px;
        }
        .report-header h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .report-header .subtitle {
            font-size: 11px;
            opacity: 0.85;
            margin-bottom: 12px;
        }
        .report-meta {
            font-size: 9.5px;
            opacity: 0.9;
            margin-top: 8px;
        }
        .report-meta div {
            display: inline-block;
            margin-right: 32px;
        }
        .report-meta span { font-weight: 600; }

        /* ── Section ── */
        .section {
            margin: 0 32px 24px 32px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0d9488;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 6px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* ── Summary Cards ── */
        .summary-grid {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }
        .summary-card {
            flex: 1;
            background: #f0fdfa;
            border: 1px solid #ccfbf1;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .summary-card .label {
            font-size: 8.5px;
            font-weight: 700;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 18px;
            font-weight: 700;
            color: #134e4a;
        }
        .summary-card .sub-value {
            font-size: 9px;
            color: #5eead4;
            margin-top: 2px;
        }

        /* ── Tables ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        thead tr {
            background: #134e4a;
            color: #ffffff;
        }
        thead th {
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tbody tr:nth-child(even) { background: #f0fdfa; }
        tbody tr:nth-child(odd)  { background: #ffffff; }
        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2f8f5;
            vertical-align: top;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 20px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .badge-success  { background: #d1fae5; color: #065f46; }
        .badge-pending  { background: #fef3c7; color: #92400e; }
        .badge-failed   { background: #fee2e2; color: #991b1b; }
        .badge-default  { background: #e0e7ff; color: #3730a3; }

        /* ── Footer ── */
        .report-footer {
            margin: 32px 32px 24px 32px;
            padding-top: 16px;
            border-top: 1px solid #ccfbf1;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 8.5px;
            color: #6b7280;
        }
        .report-footer .signature-block {
            text-align: right;
        }
        .report-footer .signature-block .role-label {
            font-weight: 700;
            color: #0d9488;
            font-size: 9px;
            margin-bottom: 32px;
        }
        .report-footer .signature-block .name-line {
            border-top: 1px solid #9ca3af;
            padding-top: 4px;
            min-width: 150px;
        }
        .empty-state {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px dashed #d1d5db;
            font-style: italic;
        }
    </style>
</head>
<body>

{{-- ════════════════════════════════════════════
     HEADER
     ════════════════════════════════════════════ --}}
<div style="margin: 0 32px 24px 32px; padding-top: 28px; padding-bottom: 12px; border-bottom: 2px solid #0d9488;">
    <h1 style="font-size: 22px; font-weight: 700; margin: 0 0 6px 0; letter-spacing: 0.5px; color: #0d9488;">Rekapan Data Laporan Panti Asuhan Dr. J. Lucas ({{ $data['period']['month'] }})</h1>
    <p style="font-size: 13px; margin: 0 0 12px 0; color: #4b5563; font-weight: 600;">-- dari {{ $data['period']['start'] }} sampai {{ $data['period']['end'] }}</p>
    
    <div style="font-size: 10px; color: #6b7280; width: 100%;">
        <span style="margin-right: 24px;">Dicetak pada: <strong style="color: #374151;">{{ $data['generated_at'] }}</strong></span>
        @if ($generatedBy)
        <span>Dicetak oleh: <strong style="color: #374151;">{{ $generatedBy }}</strong></span>
        @endif
    </div>
</div>

{{-- ════════════════════════════════════════════
     SEKSI 1: DONASI DANA
     ════════════════════════════════════════════ --}}
@if (!is_null($data['donations']))
<div class="section">
    <h2 class="section-title">1. Ringkasan Donasi Dana (Finansial)</h2>

    @php
        $dana = $data['donations']['dana'];
        $danaStatuses = [
            'SUCCESS'  => ['label' => 'Berhasil',          'badge' => 'badge-success'],
            'PENDING'  => ['label' => 'Menunggu',          'badge' => 'badge-pending'],
            'FAILED'   => ['label' => 'Gagal',             'badge' => 'badge-failed'],
            'EXPIRED'  => ['label' => 'Kedaluwarsa',       'badge' => 'badge-failed'],
        ];
    @endphp

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Transaksi</div>
            <div class="value">{{ $dana['total'] }}</div>
            <div class="sub-value">Semua status</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Terkumpul</div>
            <div class="value">Rp {{ number_format($dana['total_success_amount'], 0, ',', '.') }}</div>
            <div class="sub-value">Status SUCCESS</div>
        </div>
        <div class="summary-card">
            <div class="label">Berhasil</div>
            <div class="value">{{ $dana['count_by_status']['SUCCESS'] ?? 0 }}</div>
            <div class="sub-value">transaksi sukses</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th style="text-align:right;">Jumlah Transaksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($dana['count_by_status'] as $status => $count)
                <tr>
                    <td>
                        <span class="badge {{ $danaStatuses[$status]['badge'] ?? 'badge-default' }}">
                            {{ $danaStatuses[$status]['label'] ?? $status }}
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ $count }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-state">Tidak ada data donasi dana dari filter tanggal mulai {{ $data['period']['start'] }} sampai tanggal akhir {{ $data['period']['end'] }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ════════════════════════════════════════════
     SEKSI 2: DONASI BARANG
     ════════════════════════════════════════════ --}}
<div class="section">
    <h2 class="section-title">2. Ringkasan Donasi Barang (Fisik)</h2>

    @php
        $barang = $data['donations']['barang'];
        $barangStatuses = [
            'SUCCESS'          => ['label' => 'Diterima / Check-in',  'badge' => 'badge-success'],
            'PENDING_DELIVERY' => ['label' => 'Menunggu Pengiriman',  'badge' => 'badge-pending'],
            'REJECTED'         => ['label' => 'Ditolak',              'badge' => 'badge-failed'],
        ];
    @endphp

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Pengajuan</div>
            <div class="value">{{ $barang['total'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Diterima</div>
            <div class="value">{{ $barang['count_by_status']['SUCCESS'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Ditolak</div>
            <div class="value">{{ $barang['count_by_status']['REJECTED'] ?? 0 }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th style="text-align:right;">Jumlah Pengajuan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($barang['count_by_status'] as $status => $count)
                <tr>
                    <td>
                        <span class="badge {{ $barangStatuses[$status]['badge'] ?? 'badge-default' }}">
                            {{ $barangStatuses[$status]['label'] ?? $status }}
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ $count }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-state">Tidak ada data donasi barang dari filter tanggal mulai {{ $data['period']['start'] }} sampai tanggal akhir {{ $data['period']['end'] }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- ════════════════════════════════════════════
     SEKSI 3: KUNJUNGAN
     ════════════════════════════════════════════ --}}
@if (!is_null($data['visits']))
<div class="section">
    <h2 class="section-title">3. Ringkasan Kunjungan</h2>

    @php
        $visits = $data['visits'];
        $visitStatuses = [
            'PENDING'           => ['label' => 'Menunggu',      'badge' => 'badge-pending'],
            'APPROVED'          => ['label' => 'Disetujui',     'badge' => 'badge-success'],
            'REJECTED'          => ['label' => 'Ditolak',       'badge' => 'badge-failed'],
            'NEEDS_RESCHEDULE'  => ['label' => 'Perlu Jadwal Ulang', 'badge' => 'badge-default'],
            'COMPLETED'         => ['label' => 'Selesai',       'badge' => 'badge-success'],
            'NO_SHOW'           => ['label' => 'Tidak Hadir',   'badge' => 'badge-failed'],
        ];
    @endphp

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Pengajuan</div>
            <div class="value">{{ $visits['total'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Selesai</div>
            <div class="value">{{ $visits['count_by_status']['COMPLETED'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Tidak Hadir</div>
            <div class="value">{{ $visits['count_by_status']['NO_SHOW'] ?? 0 }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Status Kunjungan</th>
                <th style="text-align:right;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($visits['count_by_status'] as $status => $count)
                <tr>
                    <td>
                        <span class="badge {{ $visitStatuses[$status]['badge'] ?? 'badge-default' }}">
                            {{ $visitStatuses[$status]['label'] ?? $status }}
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ $count }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-state">Tidak ada data kunjungan dari filter tanggal mulai {{ $data['period']['start'] }} sampai tanggal akhir {{ $data['period']['end'] }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- ════════════════════════════════════════════
     SEKSI 4: DISTRIBUSI
     ════════════════════════════════════════════ --}}
@if (!is_null($data['distributions']))
<div class="section">
    <h2 class="section-title">4. Riwayat Distribusi Bantuan</h2>

    @php $distributions = $data['distributions']; @endphp

    @if ($distributions['total'] > 0)
        <p style="font-size:9px; color:#6b7280; margin-bottom:8px;">
            Total {{ $distributions['total'] }} catatan distribusi dari filter tanggal mulai {{ $data['period']['start'] }} sampai tanggal akhir {{ $data['period']['end'] }}.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th style="text-align:center;">Qty</th>
                    <th>Penerima</th>
                    <th>Catatan</th>
                    <th>Oleh</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($distributions['records'] as $record)
                    <tr>
                        <td style="font-weight:600;">{{ $record['item_name'] }}</td>
                        <td style="text-align:center;">{{ $record['qty'] }} {{ $record['unit'] }}</td>
                        <td>{{ $record['target_recipient'] }}</td>
                        <td style="color:#6b7280;">{{ $record['notes'] }}</td>
                        <td>{{ $record['distributed_by'] }}</td>
                        <td style="white-space:nowrap;">{{ $record['distributed_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">Tidak ada catatan distribusi dari filter tanggal mulai {{ $data['period']['start'] }} sampai tanggal akhir {{ $data['period']['end'] }}.</div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════
     FOOTER
     ════════════════════════════════════════════ --}}
<div class="report-footer">
    <div>
        <p>Dokumen ini digenerate secara otomatis oleh Sistem SIMDK.</p>
        <p style="margin-top:3px;">Laporan ini bersifat resmi dan hanya untuk keperluan internal panti.</p>
    </div>
    @if ($generatedBy)
    <div class="signature-block">
        <div class="role-label">Pengurus / Kepala Panti</div>
        <div class="name-line">{{ $generatedBy }}</div>
    </div>
    @endif
</div>

</body>
</html>
