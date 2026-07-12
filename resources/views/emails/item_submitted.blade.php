<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pengajuan Donasi Barang - Panti Asuhan Dr. J. Lucas</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #0D9488; }
        .info { background: #f0fdfa; padding: 15px; border-left: 4px solid #0D9488; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Halo, {{ $donorName }}</h2>
        <p>Pengajuan donasi barang Anda ke Panti Asuhan Dr. J. Lucas telah berhasil dicatat.</p>
        
        <div class="info">
            <p><strong>Nama Donatur:</strong> {{ $donorName }}</p>
            <p><strong>Jenis Donasi:</strong> Barang Fisik</p>
            <p><strong>Tanggal Pengajuan:</strong> {{ $date }}</p>
            <p><strong>Kode Pelacakan:</strong> {{ $trackingCode }}</p>
            <p>Status saat ini: <strong>Menunggu Kedatangan Barang (PENDING DELIVERY)</strong></p>
            <p><small>*Mohon antarkan barang Anda dalam waktu 30 jam, jika tidak maka pengajuan akan otomatis dibatalkan.</small></p>
        </div>

        <h3>Rincian Barang:</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                <tr>
                    <td>{{ $item->itemName_snapshot }}</td>
                    <td>{{ $item->qty }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <p>Silakan tunjukkan Kode Pelacakan Anda kepada pengurus panti saat menyerahkan barang.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
