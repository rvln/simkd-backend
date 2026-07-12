<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Permintaan Ubah Jadwal Kunjungan</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; background-color: #f7f3ef; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #D97706; }
        .info { background: #fffbeb; padding: 15px; border-left: 4px solid #F59E0B; margin-bottom: 20px; }
        .notes { background: #eff6ff; padding: 15px; border: 1px solid #bfdbfe; border-radius: 8px; margin-bottom: 20px; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; text-align: center; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #D97706; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Pemberitahuan Ubah Jadwal Kunjungan</h2>
        <p>Halo, <strong>{{ $visit->visitor_name ?? $visit->user->name }}</strong>.</p>
        <p>Terima kasih atas niat baik Anda untuk mengunjungi Panti Asuhan Dr. J. Lucas. Namun, kami memohon maaf bahwa sesi yang Anda pilih saat ini berhalangan.</p>
        
        <div class="notes">
            <strong>Catatan dari Admin:</strong>
            <p style="margin-top: 5px; font-style: italic;">"{{ $visit->admin_notes }}"</p>
        </div>

        <p>Silakan masuk ke akun Anda dan lakukan pengajuan ulang (Ubah Jadwal) sesuai dengan saran dari kami.</p>

        <a href="{{ url(config('app.frontend_url') . '/jadwal-kunjungan') }}" class="btn">Ubah Jadwal Sekarang</a>

        <div class="footer">
            &copy; {{ date('Y') }} Panti Asuhan Dr. J. Lucas. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
