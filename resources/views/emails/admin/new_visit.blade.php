<!DOCTYPE html>
<html>
<head>
    <title>Pengajuan Kunjungan Baru</title>
</head>
<body>
    <h2>Halo Admin,</h2>
    <p>Terdapat pengajuan kunjungan baru di Panti Asuhan Dr. J. Lucas.</p>
    
    <ul>
        <li><strong>Nama Pengunjung:</strong> {{ $visit->user->name ?? 'Anonim' }}</li>
        <li><strong>Email:</strong> {{ $visit->user->email ?? '-' }}</li>
        <li><strong>Telepon:</strong> {{ $visit->user->phone ?? '-' }}</li>
        <li><strong>Tanggal & Sesi:</strong> {{ \Carbon\Carbon::parse($visit->capacity->date)->locale('id')->translatedFormat('l, d F Y') }} ({{ $timeStr }})</li>
        <li><strong>Tipe Pengunjung:</strong> {{ $visitorTypeStr }}</li>
        <li><strong>Rincian Tujuan:</strong> {{ $visit->purpose ?? 'Tidak ada rincian tujuan khusus' }}</li>
    </ul>

    <p>Silakan login ke Dashboard SIMKD untuk melakukan persetujuan (Approve/Reject).</p>

    <p>Terima kasih,<br>Sistem Manajemen SIMKD</p>
</body>
</html>
