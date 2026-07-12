<!DOCTYPE html>
<html>
<head>
    <title>Pengajuan Donasi Baru</title>
</head>
<body>
    <h2>Halo Admin,</h2>
    <p>Terdapat pengajuan donasi baru di Panti Asuhan Dr. J. Lucas.</p>
    
    <ul>
        <li><strong>Nama Donatur:</strong> {{ $donation->donorName ?? ($donation->user->name ?? 'Hamba Allah') }}</li>
        <li><strong>Email:</strong> {{ $donation->donorEmail ?? ($donation->user->email ?? '-') }}</li>
        <li><strong>Tipe Donasi:</strong> {{ $typeStr }}</li>
        @if($donation->type === 'DANA' || $donation->type === \App\Enums\DonationTypeEnum::DANA)
            <li><strong>Metode Pembayaran:</strong> {{ $donation->payment_channel }}</li>
            <li><strong>Jumlah Donasi:</strong> Rp {{ number_format($donation->amount, 0, ',', '.') }}</li>
        @else
            <li><strong>Rencana Pengiriman:</strong> {{ $deliveryStr }}</li>
        @endif
        <li><strong>Kode Pelacakan:</strong> {{ $donation->tracking_code }}</li>
    </ul>

    <p>Silakan login ke Dashboard SIMKD untuk memeriksa detail pengajuan ini.</p>

    <p>Terima kasih,<br>Sistem Manajemen SIMKD</p>
</body>
</html>
