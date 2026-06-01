<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to payment…</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; margin: 0; display: flex; min-height: 100vh; align-items: center; justify-content: center; color: #374151; }
        .card { background: #fff; border-radius: 12px; padding: 32px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        button { margin-top: 16px; padding: 12px 20px; border: 0; border-radius: 8px; background: #111827; color: #fff; font-size: 14px; cursor: pointer; }
    </style>
</head>
<body onload="document.forms[0].submit()">
    <div class="card">
        <p>Redirecting to the payment gateway…</p>

        <form method="post" action="{{ $action }}">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript>
                <p>JavaScript is disabled. Click below to continue.</p>
                <button type="submit">Continue to payment</button>
            </noscript>
        </form>
    </div>
</body>
</html>
