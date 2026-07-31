@php
    function fmtStatus($status) {
        return match ($status) {
            "Draft"     => "Draft",
            "Submitted" => "Selesai",
            default     => "Dalam Proses",
        };
    }

    $hasPhotos = false;
    foreach ($categories as $category) {
        foreach ($category['questions'] as $question) {
            if (!empty($question['photos'])) {
                $hasPhotos = true;
                break 2;
            }
        }
    }
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $audit['document_id'] }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #222;
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        .report {
            padding: 0;
        }

        .large-text {
            font-size: 18px;
            font-weight: 800;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
            width: 100%;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .status {
            border: 1px solid #999;
            padding: 5px 12px;
            font-size: 10pt;
            border-radius: 4px;
            font-weight: bold;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .summary td {
            border: 1px solid #ddd;
            font-size: 9.5pt;
            padding: 6px;
        }

        .summary td:nth-child(odd) {
            width: 140px;
            background: #f5f5f5;
            font-weight: bold;
        }

        hr {
            border: none;
            border-top: 1px solid #999;
            margin: 15px 0;
        }

        h2 {
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .category {
            width: 100%;
            margin-bottom: 15px;
        }

        .category-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 10px;
            background: #f2f2f2;
            border: 1px solid #ccc;
        }

        .question {
            border: 1px solid #ddd;
            border-top: none;
            padding: 6px 10px;
            break-inside: avoid;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .score {
            min-width: 25px;
            text-align: right;
            font-size: 11pt;
            font-weight: 700;
        }

        .remark {
            margin-top: 4px;
            margin-left: 20px;
            padding: 4px 8px;
            font-size: 9.5pt;
            border-left: 3px solid #bbb;
            background: #fcfcfc;
        }

        .photo-category {
            margin-bottom: 25px;
            break-inside: avoid;
        }

        .photo-question-title {
            font-size: 12pt;
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }

        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .gallery-photo {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border: 1px solid #bbb;
            border-radius: 4px;
        }

        .annotated-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            break-inside: avoid;
        }

        .annotated-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border: 1px solid #eee;
            padding: 8px;
            border-radius: 6px;
        }

        .annotated-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .annotated-notes {
            flex: 1;
            font-size: 9pt;
        }

        .annotated-notes p { margin: 0 0 5px; }

        .score-red    { color: #dc2626; }
        .score-orange { color: #f97316; }
        .score-yellow { color: #ca8a04; }
        .score-blue   { color: #2563eb; }
        .score-green  { color: #16a34a; }
        .score-na     { color: #4b5563; }

        .signature {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .signature td {
            text-align: center;
            vertical-align: top;
            padding: 0 15px;
        }

        .sig-title {
            font-weight: bold;
            color: #666;
            margin-bottom: 10px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .sig-name {
            font-weight: bold;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .verification-photo {
            width: 180px;
            height: 120px;
            object-fit: contain;
            border: 1px solid #bbb;
            background: #f9f9f9;
        }

        .print-toolbar {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .print-toolbar button {
            padding: 12px 30px;
            background: #b63352;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        @media print {
            .print-toolbar { display: none; }
            .report { padding: 0; }
            * { -webkit-print-color-adjust: exact !important; }
        }
    </style>
</head>
<body class="report">

    <div class="print-toolbar">
        <button type="button" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            PRINT / SAVE AS PDF
        </button>
    </div>

    <div class="header">
        <div>
            <h1>{{ $audit['document_id'] }}</h1>
        </div>
        <div class="status">
            {{ fmtStatus($audit['status']) }}
        </div>
    </div>

    <table class="summary">
        <tr>
            <td>Departemen/Divisi</td>
            <td>{{ $audit['department_name'] }}</td>
            <td>Auditor</td>
            <td>{{ $audit['auditor_name'] }}</td>
        </tr>
        <tr>
            <td>Tanggal Audit</td>
            <td>{{ \Carbon\Carbon::parse($audit['audit_date'])->translatedFormat('d F Y') }}</td>
            <td>Tanggal Selesai</td>
            <td>{{ $audit['submitted_at'] ? \Carbon\Carbon::parse($audit['submitted_at'])->translatedFormat('d F Y, H:i') : '-' }}</td>
        </tr>
        <tr>
            <td>Nilai Total</td>
            <td class="large-text">{{ number_format($audit['total_score'], 1) }} / {{ number_format($audit['max_score'], 1) }}</td>
            <td>Persentase</td>
            <td class="large-text" style="color: #b63352">{{ number_format($audit['percentage'], 2) }}%</td>
        </tr>
    </table>

    <hr>

    <h2>Hasil Penilaian</h2>

    @foreach($categories as $category)
    <div class="category">
        <div class="category-title">
            <span>{{ $category['name'] }}</span>
            <span class="category-score">
                {{ number_format($category['total_score'], 1) }} / {{ number_format($category['max_score'], 1) }}
                ({{ round($category['percentage']) }}%)
            </span>
        </div>

        @foreach($category['questions'] as $index => $question)
            <div class="question">
                <div class="question-header">
                    <div>{{ $index + 1 }}. {{ $question['question'] }}</div>
                    @php
                        $score = $question['response']['score'];
                        $isNa = $question['response']['is_na'];
                        $scoreText = $isNa ? 'N/A' : ($score !== null ? rtrim(rtrim(number_format($score, 1), '0'), '.') : '-');
                        $scoreClass = 'score-na';
                        if (!$isNa && $score !== null) {
                            $val = (float)$score;
                            if ($val == 0) $scoreClass = 'score-red';
                            elseif ($val == 0.5) $scoreClass = 'score-orange';
                            elseif ($val == 1.0) $scoreClass = 'score-yellow';
                            elseif ($val == 1.5) $scoreClass = 'score-blue';
                            elseif ($val == 2.0) $scoreClass = 'score-green';
                        }
                    @endphp
                    <div class="score {{ $scoreClass }}">{{ $scoreText }}</div>
                </div>
                @if(!empty($question['response']['remark']))
                    <div class="remark">
                        {!! nl2br(e($question['response']['remark'])) !!}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endforeach

    <hr>

    <table class="signature">
        <tr>
            <td>
                <div class="sig-title">Auditor</div>
                <div class="sig-name">{{ $audit['auditor_name'] }}</div>
            </td>
            <td>
                <div class="sig-title">Foto Verifikasi</div>
                @if($audit['verification_photo'])
                    <img src="{{ $audit['verification_photo'] }}" class="verification-photo">
                @else
                    <div style="height: 100px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #999">Tidak ada foto</div>
                @endif
            </td>
            <td>
                <div class="sig-title">Auditee / PIC</div>
                <div class="sig-name">{{ $audit['auditee_name'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if($hasPhotos)
        <div style="page-break-before: always;"></div>
        <h2>Dokumentasi Foto Temuan</h2>

        @foreach($categories as $category)
            @php
                $photoQuestions = array_filter($category['questions'], fn($q) => !empty($q['photos']));
            @endphp

            @if(!empty($photoQuestions))
                <div class="photo-category">
                    <h3 style="background: #f9f9f9; padding: 5px 10px; border-left: 4px solid #b63352;">{{ $category['name'] }}</h3>

                    @foreach($photoQuestions as $q)
                        <div class="photo-question">
                            <div class="photo-question-title">{{ $q['question'] }} ({{ count($q['photos']) }} foto)</div>

                            @php
                                $gallery = array_filter($q['photos'], fn($p) => empty($p['remark']) && empty($p['action']));
                                $annotated = array_filter($q['photos'], fn($p) => !empty($p['remark']) || !empty($p['action']));
                            @endphp

                            @if(!empty($gallery))
                                <div class="photo-gallery">
                                    @foreach($gallery as $p)
                                        <img src="{{ $p['photo_path'] }}" class="gallery-photo">
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($annotated))
                                @foreach(array_chunk($annotated, 2) as $row)
                                    <div class="annotated-row">
                                        @foreach($row as $p)
                                            <div class="annotated-item">
                                                <img src="{{ $p['photo_path'] }}" class="annotated-photo">
                                                <div class="annotated-notes">
                                                    @if($p['remark']) <p><strong>Temuan:</strong> {{ $p['remark'] }}</p> @endif
                                                    @if($p['action']) <p><strong>Rekomendasi:</strong> {{ $p['action'] }}</p> @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif

</body>
</html>
