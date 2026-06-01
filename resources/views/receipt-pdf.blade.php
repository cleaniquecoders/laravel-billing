<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $invoice->number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 12px; margin: 0; padding: 32px; }
        .row { width: 100%; }
        .row:after { content: ""; display: table; clear: both; }
        .col { float: left; width: 50%; }
        .right { text-align: right; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .amount { font-size: 28px; font-weight: bold; margin: 8px 0 0; }
        .muted { color: #6b7280; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e5e7eb; }
        td.right { text-align: right; }
        .section { margin-top: 24px; }
    </style>
</head>
<body>
    <div class="row">
        <div class="col">
            <h1>RECEIPT</h1>
            <div class="muted">Payment received</div>
            <div class="amount">{{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}</div>
        </div>
        <div class="col right">
            @if(!empty($company['name']))
                <strong>{{ $company['name'] }}</strong><br>
            @endif
            @if(!empty($company['ssm']))<span class="muted">SSM: {{ $company['ssm'] }}</span><br>@endif
            @if(!empty($company['sst']))<span class="muted">SST: {{ $company['sst'] }}</span><br>@endif
            @if(!empty($company['email']))<span class="muted">{{ $company['email'] }}</span>@endif
        </div>
    </div>

    <div class="section row">
        <div class="col">
            <div class="label">Billed to</div>
            <strong>{{ $billable?->billingName() }}</strong><br>
            <span class="muted">{{ $billable?->billingEmail() }}</span>
        </div>
    </div>

    <table>
        <tr>
            <td class="muted">Invoice number</td>
            <td class="right">{{ $invoice->number }}</td>
        </tr>
        <tr>
            <td class="muted">Payment date</td>
            <td class="right">{{ optional($invoice->paid_at)->toDayDateTimeString() }}</td>
        </tr>
        <tr>
            <td class="muted">Payment method</td>
            <td class="right">{{ $invoice->paymentMethod() ?? '—' }}</td>
        </tr>
        <tr>
            <td class="muted">Amount paid</td>
            <td class="right"><strong>{{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}</strong></td>
        </tr>
    </table>

    <div class="section muted">This is a payment receipt for invoice {{ $invoice->number }}.</div>
</body>
</html>
