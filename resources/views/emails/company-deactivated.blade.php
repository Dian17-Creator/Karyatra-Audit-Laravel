<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perusahaan Auditra Dinonaktifkan</title>
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
            background-color: #fef2f2;
            color: #dc2626;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #f8fafc;
            border-left: 4px solid #ef4444;
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
                <div class="company-badge">Perusahaan: {{ $owner->cperusahaan }} (Nonaktif Sementara)</div>
                
                <p>Perusahaan Anda di platform Auditra telah berhasil <strong>dinonaktifkan sementara</strong> sesuai dengan permintaan Owner.</p>

                <div class="info-box">
                    <strong>Catatan Penting Penonaktifan:</strong>
                    <ul>
                        <li>Seluruh pengguna dalam perusahaan Anda terblokir sementara dan tidak dapat mengakses fitur aplikasi.</li>
                        <li>Seluruh data perusahaan Anda tetap <strong>tersimpan dengan aman</strong> dan tidak dihapus.</li>
                        <li>Masa berlangganan Pro (jika ada) tetap berjalan dan <strong>tidak dijeda</strong> selama penonaktifan sementara.</li>
                        <li>Anda (Owner) dapat mengaktifkan kembali akun perusahaan kapan saja melalui menu Pengaturan Akun.</li>
                    </ul>
                </div>

                <p>Jika Anda tidak merasa melakukan tindakan ini, silakan segera hubungi Tim Dukungan Auditra.</p>
            </div>
            <div class="footer">
                &copy; {{ date('Y') }} Auditra System. Hak Cipta Dilindungi.<br>
                Email ini dikirim secara otomatis sebagai pemberitahuan resmi.
            </div>
        </div>
    </div>
</body>
</html>
