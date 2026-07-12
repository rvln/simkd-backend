<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Kunjungan Disetujui</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #2563EB; }
        .info { background: #eff6ff; padding: 15px; border-left: 4px solid #2563EB; margin-bottom: 20px; }
        .rules { background: #fffbeb; padding: 15px; border: 1px solid #fcd34d; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Kunjungan Disetujui!</h2>
        <p>Halo, <strong>{{ $visitorName }}</strong>.</p>
        <p>Pengajuan jadwal kunjungan Anda ke Panti Asuhan Dr. J. Lucas telah <strong>disetujui</strong>.</p>
        
        <div class="info">
            <p><strong>Tanggal:</strong> {{ $date }}</p>
            <p><strong>Sesi:</strong> {{ $time }}</p>
            <p><strong>Tipe Pengunjung:</strong> {{ $visitorType }}</p>
            <p><strong>Rincian Tujuan:</strong> {{ $purpose }}</p>
        </div>

        <div class="rules">
            <strong>Tata Tertib Kunjungan:</strong>
            <ul>
                <li>Datang tepat waktu sesuai sesi yang dipilih.</li>
                <li>Berpakaian sopan dan rapi.</li>
                <li>Dilarang mengambil foto/video anak-anak secara langsung untuk melindungi privasi mereka.</li>
            </ul>
        </div>

        <p>Kami sangat menantikan kedatangan Anda. Sampai jumpa!</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
