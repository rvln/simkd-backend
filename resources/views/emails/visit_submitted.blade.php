<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pengajuan Jadwal Kunjungan</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #16A34A; }
        .info { background: #f0fdf4; padding: 15px; border-left: 4px solid #16A34A; margin-bottom: 20px; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Halo, {{ $visitorName }}</h2>
        <p>Pengajuan jadwal kunjungan Anda ke Panti Asuhan Dr. J. Lucas telah kami terima dan saat ini berstatus <strong>Menunggu Persetujuan</strong>.</p>
        
        <div class="info">
            <p><strong>Tipe Pengunjung:</strong> {{ $visitorType }}</p>
            <p><strong>Rincian Tujuan:</strong> {{ $purpose }}</p>
            <p><strong>Tanggal:</strong> {{ $date }}</p>
            <p><strong>Sesi:</strong> {{ $time }}</p>
        </div>

        <p>Pengurus kami akan meninjau jadwal Anda dan mengirimkan konfirmasi persetujuan dalam waktu 1x24 jam.</p>
        
        <p>Terima kasih atas niat baik Anda!</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
