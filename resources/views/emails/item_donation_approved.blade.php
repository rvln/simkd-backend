<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Donasi Barang Anda Telah Disetujui</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #059669; }
        .info { background: #ecfdf5; padding: 15px; border-left: 4px solid #10b981; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { border: 1px solid #d1d5db; padding: 8px 12px; text-align: left; }
        .table th { background-color: #f3f4f6; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Donasi Barang Berhasil Divalidasi!</h2>
        <p>Halo, <strong>{{ $donation->donorName ?? 'Dermawan' }}</strong>.</p>
        <p>Kabar baik! Donasi barang Anda dengan kode pelacakan <strong>{{ $donation->tracking_code }}</strong> telah kami terima dan disetujui (divalidasi) oleh admin.</p>
        
        <div class="info">
            <p>Donasi barang ini telah masuk ke inventaris Panti Asuhan Dr. J. Lucas dan siap didistribusikan kepada anak-anak yang membutuhkan.</p>
        </div>

        <p>Terima kasih yang sebesar-besarnya atas kebaikan hati Anda. Bantuan Anda sangat berarti bagi mereka.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
