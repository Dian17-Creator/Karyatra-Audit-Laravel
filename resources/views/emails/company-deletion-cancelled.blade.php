<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penghapusan Perusahaan Auditra Dibatalkan</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #f4f6f9;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            color: #94a3b8;
            margin: 6px 0 0 0;
            font-size: 13px;
        }
        .body {
            padding: 40px;
            color: #334155;
            line-height: 1.6;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .company-badge {
            display: inline-block;
            background-color: #f0fdf4;
            color: #16a34a;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #f8fafc;
            border-left: 4px solid #22c55e;
            padding: 16px 20px;
            border-radius: 4px;
            font-size: 14px;
            color: #334155;
            margin: 20px 0;
        }
        .info-box ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        .info-box li {
            margin-bottom: 6px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>AUDITRA</h1>
                <p>Platform Manajemen Audit & Stock Opname</p>
            </div>
            <div class="body">
                <div class="greeting">Halo, {{ $owner->cnamalengkap }}!</div>
                <div class="company-badge">Perusahaan: {{ $owner->cperusahaan }}</div>
                
                <p>Permintaan penghapusan akun perusahaan Anda di platform Auditra telah <strong>berhasil dibatalkan</strong>.</p>

                <div class="info-box">
                    <strong>Status Akun Saat Ini:</strong>
                    <ul>
                        @if($isInactive)
                            <li>Perusahaan kembali ke status <strong>Nonaktif Sementara</strong>. Seluruh data tetap aman.</li>
                            <li>Anda (Owner) dapat mengaktifkan kembali operasional perusahaan kapan saja via aplikasi.</li>
                        @else
                            <li>Perusahaan kembali berstatus <strong>Aktif Penuh</strong>.</li>
                            <li>Seluruh pengguna perusahaan dapat langsung kembali login dan melakukan transaksi audit/opname.</li>
                        @endif
                    </ul>
                </div>

                <p>Terima kasih telah memilih untuk terus mempercayakan manajemen audit Anda bersama Auditra.</p>
            </div>
            <div class="footer">
                &copy; {{ date('Y') }} Auditra System. Hak Cipta Dilindungi.<br>
                Email ini dikirim secara otomatis sebagai pemberitahuan resmi.
            </div>
        </div>
    </div>
</body>
</html>
