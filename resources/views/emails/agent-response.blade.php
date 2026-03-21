<!DOCTYPE html>
<html lang="bg">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отговор от офис агента</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111;">
    <p>Здравейте,</p>

    <p>Изпращаме ви избран отговор от офис агента:</p>

    <blockquote style="margin: 16px 0; padding: 12px 16px; border-left: 3px solid #ccc; background: #fafafa; white-space: pre-wrap;">
        {{ $responseText }}
    </blockquote>

    <p style="color: #666; font-size: 12px;">Изпратено автоматично от {{ config('app.name') }}.</p>
</body>

</html>