<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pemberitahuan Status Donasi Dana</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #DC2626; }
        .notes { background: #fef2f2; padding: 15px; border-left: 4px solid #DC2626; border-radius: 8px; margin-bottom: 20px; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Mohon Maaf, Donasi Dana Ditolak</h2>
        <p>Halo, <strong>{{ $donation->donorName ?? 'Dermawan' }}</strong>.</p>
        <p>Terima kasih atas partisipasi Anda dalam berdonasi secara manual ke Panti Asuhan Dr. J. Lucas. Namun, setelah kami melakukan pengecekan terhadap bukti transfer yang Anda lampirkan, donasi dengan jumlah <strong>Rp {{ number_format($donation->amount, 0, ',', '.') }}</strong> tidak dapat kami validasi.</p>
        
        <div class="notes">
            <strong>Catatan:</strong>
            <p style="margin-top: 5px;">Hal ini bisa terjadi karena bukti transfer yang tidak valid, buram, atau tidak sesuai dengan mutasi rekening kami. Jika Anda merasa ini adalah kesalahan, silakan hubungi pihak panti asuhan untuk verifikasi lebih lanjut dengan menyertakan bukti transfer yang jelas.</p>
        </div>

        <p>Kami sangat menghargai niat baik Anda dan berharap Anda dapat memaklumi prosedur keamanan kami.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
