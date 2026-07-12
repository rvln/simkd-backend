<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pemberitahuan Status Donasi Barang</title>
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
        <h2>Mohon Maaf, Donasi Barang Tidak Dapat Diterima</h2>
        <p>Halo, <strong>{{ $donation->donorName ?? 'Dermawan' }}</strong>.</p>
        <p>Terima kasih atas niat baik Anda untuk berdonasi ke Panti Asuhan Dr. J. Lucas. Namun, setelah melakukan proses pengecekan untuk donasi dengan kode pelacakan <strong>{{ $donation->tracking_code }}</strong>, kami menemukan bahwa donasi tersebut tidak dapat kami proses lebih lanjut.</p>
        
        <div class="notes">
            <strong>Catatan / Alasan:</strong>
            <p style="margin-top: 5px; font-style: italic;">"{{ $reason }}"</p>
        </div>

        <p>Kami sangat menghargai kebaikan hati Anda dan berharap kejadian ini tidak menyurutkan niat Anda untuk berbagi di lain kesempatan.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
