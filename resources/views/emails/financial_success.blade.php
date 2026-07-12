<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tanda Terima Donasi Finansial</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #2563EB; }
        .info { background: #f0fdf4; padding: 15px; border-left: 4px solid #16A34A; margin-bottom: 20px; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Terima Kasih, {{ $donorName }}!</h2>
        <p>Kami telah menerima donasi finansial Anda untuk Panti Asuhan Dr. J. Lucas.</p>
        
        <div class="info">
            <p><strong>Nominal:</strong> Rp {{ number_format($amount, 0, ',', '.') }}</p>
            <p><strong>Tanggal:</strong> {{ $date }}</p>
        </div>

        <p>Bukti transparansi dan jejak kebaikan dari donasi Anda dapat dilihat secara langsung pada halaman utama website kami.</p>
        
        <p>Semoga kebaikan Anda membawa berkah bagi anak-anak di panti asuhan kami.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
