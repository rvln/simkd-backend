<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Transfer Donasi</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1e293b;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #64748b;
        }
        .status-badge {
            display: inline-block;
            background-color: #22c55e;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
            letter-spacing: 1px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .details-table th, .details-table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .details-table th {
            color: #64748b;
            font-weight: normal;
            width: 40%;
        }
        .details-table td {
            font-weight: bold;
            color: #0f172a;
        }
        .amount-row td {
            font-size: 18px;
            color: #2563eb;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bukti Transfer Donasi</h1>
            <p>Panti Asuhan Dr. J. Lucas</p>
            <div class="status-badge">LUNAS / PAID</div>
        </div>

        <table class="details-table">
            <tr>
                <th>ID Transaksi / Order ID</th>
                <td>{{ $donation->order_id ?? $donation->id }}</td>
            </tr>
            <tr>
                <th>Tanggal Pembayaran</th>
                <td>{{ $donation->updated_at ? $donation->updated_at->format('d M Y H:i T') : $donation->created_at->format('d M Y H:i T') }}</td>
            </tr>
            <tr>
                <th>Metode Pembayaran</th>
                <td>{{ strtoupper(str_replace('_', ' ', $donation->payment_type ?? 'Online Payment')) }}</td>
            </tr>
            <tr>
                <th>Nama Donatur</th>
                <td>{{ $donation->donorName }}</td>
            </tr>
            <tr class="amount-row">
                <th>Total Donasi</th>
                <td>Rp {{ number_format($donation->amount, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="footer">
            <p>Dokumen ini adalah bukti pembayaran yang sah dan diterbitkan oleh sistem secara otomatis.<br>
            Terima kasih atas kepedulian dan kontribusi Anda.</p>
        </div>
    </div>
</body>
</html>
