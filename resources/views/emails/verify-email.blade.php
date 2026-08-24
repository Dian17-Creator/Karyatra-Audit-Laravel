<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email Auditra</title>
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
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transition: all 0.3s ease;
        }
        .notice-box {
            background-color: #fffbe6;
            border-left: 4px solid #f59e0b;
            padding: 14px 18px;
            border-radius: 4px;
            font-size: 13px;
            color: #92400e;
            margin-top: 24px;
        }
        .url-fallback {
            margin-top: 28px;
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
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
                <div class="greeting">Halo, {{ $user->cnamalengkap }}!</div>
                <div class="company-badge">Perusahaan: {{ $user->cperusahaan }}</div>
                
                <p>Terima kasih telah mendaftarkan akun pemilik perusahaan (Owner) di sistem Auditra. Untuk mengaktifkan akun perusahaan secara penuh dan keluar dari mode trial, silakan verifikasi alamat email Anda dengan menekan tombol di bawah ini:</p>

                <div class="btn-wrapper">
                    <a href="{{ $verificationUrl }}" class="btn" target="_blank">Verifikasi Email Sekarang</a>
                </div>

                <div class="notice-box">
                    <strong>Penting:</strong> Tautan verifikasi email ini hanya berlaku selama <strong>48 jam</strong> sejak pendaftaran atau permintaan kirim ulang dibuat.
                </div>

                <div class="url-fallback">
                    Jika tombol di atas tidak dapat diklik, salin dan tempel tautan berikut ke peramban (browser) Anda:<br>
                    <a href="{{ $verificationUrl }}" style="color: #2563eb;">{{ $verificationUrl }}</a>
                </div>
            </div>
            <div class="footer">
                &copy; {{ date('Y') }} Auditra System. Hak Cipta Dilindungi.<br>
                Jika Anda tidak merasa mendaftar di Auditra, silakan abaikan email ini.
            </div>
        </div>
    </div>
</body>
</html>
