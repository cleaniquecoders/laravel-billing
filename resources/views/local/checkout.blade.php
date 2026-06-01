<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local Checkout</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; margin: 0; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 12px; padding: 32px; width: 360px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 13px; margin: 0 0 20px; }
        .amount { font-size: 28px; font-weight: 700; margin: 16px 0; }
        .row { display: flex; gap: 12px; margin-top: 20px; }
        button { flex: 1; padding: 12px; border: 0; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .approve { background: #16a34a; color: #fff; }
        .decline { background: #e5e7eb; color: #111827; }
        .badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: 11px; padding: 2px 8px; border-radius: 999px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">LOCAL GATEWAY · NO REAL MONEY</span>
        <h1 style="margin-top:12px;">Confirm your subscription</h1>
        <p class="muted">This is the bundled development checkout.</p>

        <div class="amount">
            {{ number_format(($payload['amount_cents'] ?? 0) / 100, 2) }}
            {{ config('billing.currency', 'MYR') }}
        </div>

        <form method="POST" action="{{ route('billing.local.callback') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="row">
                <button class="approve" name="decision" value="approve">Approve</button>
                <button class="decline" name="decision" value="decline">Decline</button>
            </div>
        </form>
    </div>
</body>
</html>
