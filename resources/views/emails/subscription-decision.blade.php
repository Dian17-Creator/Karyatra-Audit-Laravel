<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keputusan Langganan Auditra</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .header {
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header.approved {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .header.rejected {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 30px 25px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .details-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .details-row:last-child {
            border-bottom: none;
        }
        .details-label {
            font-weight: 600;
            color: #64748b;
        }
        .details-value {
            font-weight: 700;
            color: #1e293b;
        }
        .note-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 25px;
            color: #991b1b;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ $subscription->cstatus }}">
            <h1>
                @if($subscription->cstatus === 'approved')
                    Langganan Berhasil Disetujui! 🎉
                @else
                    Pengajuan Langganan Belum Disetujui ⚠️
                @endif
            </h1>
        </div>

        <div class="content">
            <p class="greeting">
                Halo <strong>{{ $owner->cnamalengkap ?? 'Pengguna Auditra' }}</strong>,<br>
                @if($subscription->cstatus === 'approved')
                    Pengajuan berlangganan akun Anda untuk <strong>{{ $subscription->cperusahaan ?? $owner->cperusahaan }}</strong> telah disetujui oleh tim Finance kami. Akun Anda kini berstatus <strong>Auditra Pro</strong>.
                @else
                    Mohon maaf, pengajuan langganan Anda untuk <strong>{{ $owner->cperusahaan }}</strong> belum dapat disetujui saat ini.
                @endif
            </p>

            <div class="details-box">
                <div class="details-row">
                    <span class="details-label">Paket Langganan:</span>
                    <span class="details-value">{{ $subscription->cplan_name }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Durasi:</span>
                    <span class="details-value">{{ $subscription->nduration_months }} Bulan</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Total Pembayaran:</span>
                    <span class="details-value">Rp {{ number_format($subscription->namount, 0, ',', '.') }}</span>
                </div>
                @if($subscription->cstatus === 'approved')
                <div class="details-row">
                    <span class="details-label">Mulai Berlaku:</span>
                    <span class="details-value">{{ $subscription->dstart ? $subscription->dstart->format('d M Y H:i') : '-' }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Berlaku Sampai:</span>
                    <span class="details-value">{{ $subscription->dend ? $subscription->dend->format('d M Y H:i') : '-' }}</span>
                </div>
                @endif
            </div>

            @if($subscription->cstatus === 'rejected' && !empty($subscription->cnote))
                <div class="note-box">
                    <strong>Catatan / Alasan Penolakan:</strong><br>
                    {{ $subscription->cnote }}
                </div>
            @endif

            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                @if($subscription->cstatus === 'approved')
                    Anda sekarang dapat menikmati seluruh fitur Pro termasuk ekspor laporan PDF tanpa batas dan upload foto bukti temuan.
                @else
                    Silakan periksa kembali bukti transfer atau informasi pengajuan Anda, dan Anda dapat mengajukan ulang melalui aplikasi Auditra.
                @endif
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Auditra Platform. All rights reserved.
        </div>
    </div>
</body>
</html>
