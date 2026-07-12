<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Menunggu Pembayaran Donasi - Panti Asuhan Dr. J. Lucas</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #f59e0b; }
        .info { background: #fffbeb; padding: 15px; border-left: 4px solid #f59e0b; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #3b82f6; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Halo, {{ $donorName }}</h2>
        <p>Terima kasih atas niat baik Anda untuk berdonasi ke Panti Asuhan Dr. J. Lucas.</p>
        <p>Saat ini, status donasi Anda adalah <strong>Menunggu Pembayaran</strong>.</p>
        
        <div class="info">
            <p><strong>Nominal:</strong> Rp {{ number_format($amount, 0, ',', '.') }}</p>
            <p><strong>Tanggal Pengajuan:</strong> {{ $date }}</p>
            <p style="margin-top: 20px;">
                <a href="{{ $invoiceUrl }}" class="btn" style="color: #fff;">Lanjutkan Pembayaran</a>
            </p>
        </div>

        <p>Silakan klik tombol di atas untuk melanjutkan pembayaran Anda melalui halaman tagihan (invoice), atau membatalkannya jika Anda ingin membuat nominal donasi baru.</p>

        <p>Jika Anda tidak merasa melakukan donasi ini, Anda dapat mengabaikan email ini.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
