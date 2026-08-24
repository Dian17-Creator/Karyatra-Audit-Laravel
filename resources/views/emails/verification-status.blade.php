<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Verifikasi Email - Auditra</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #f8fafc;
        }
        .card {
            background-color: #ffffff;
            color: #1e293b;
            max-width: 480px;
            width: 90%;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px auto;
            font-size: 36px;
        }
        .icon.success {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .icon.error {
            background-color: #fee2e2;
            color: #dc2626;
        }
        h2 {
            margin: 0 0 12px 0;
            font-size: 22px;
            font-weight: 700;
        }
        p {
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }
        .company-info {
            background-color: #f1f5f9;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            color: #334155;
            font-weight: 600;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <div class="card">
        @if($success)
            <div class="icon success">✓</div>
            <h2>Verifikasi Berhasil</h2>
        @else
            <div class="icon error">✕</div>
            <h2>Verifikasi Gagal</h2>
        @endif

        <p>{{ $message }}</p>

        @if(isset($user) && $user)
            <div class="company-info">
                {{ $user->cnamalengkap }} &bull; {{ $user->cperusahaan }}
            </div>
        @endif
    </div>
</body>
</html>
