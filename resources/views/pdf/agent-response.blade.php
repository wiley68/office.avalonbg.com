<!DOCTYPE html>
<html lang="bg">

<head>
    <meta charset="UTF-8">
    <title>Отговор от офис агента</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            color: #111;
            margin: 24px;
        }

        .meta {
            font-size: 9pt;
            color: #666;
            margin-bottom: 16px;
        }

        pre {
            font-family: DejaVu Sans, sans-serif;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            line-height: 1.45;
        }
    </style>
</head>

<body>
    <p class="meta">{{ $appName }} · {{ $generatedAt }}</p>
    <pre>{{ $content }}</pre>
</body>

</html>