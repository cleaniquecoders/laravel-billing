<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 12px; margin: 0; padding: 32px; }
        .row { width: 100%; }
        .row:after { content: ""; display: table; clear: both; }
        .col { float: left; width: 50%; }
        .right { text-align: right; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e5e7eb; }
        th.right, td.right { text-align: right; }
        .total td { font-weight: bold; border-top: 2px solid #111827; border-bottom: none; }
        .section { margin-top: 24px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
    </style>
</head>
<body>
    <div class="row">
        <div class="col">
            <h1>INVOICE</h1>
            <div class="muted">{{ $invoice->number }}</div>
        </div>
        <div class="col right">
            @if(!empty($company['name']))
                <strong>{{ $company['name'] }}</strong><br>
            @endif
            @if(!empty($company['ssm']))<span class="muted">SSM: {{ $company['ssm'] }}</span><br>@endif
            @if(!empty($company['sst']))<span class="muted">SST: {{ $company['sst'] }}</span><br>@endif
            @if(!empty($company['email']))<span class="muted">{{ $company['email'] }}</span><br>@endif
            @if(!empty($company['website']))<span class="muted">{{ $company['website'] }}</span>@endif
        </div>
    </div>

    @if(!empty(array_filter($company['address'] ?? [])))
        <div class="section">
            <div class="label">From</div>
            {{ $company['address']['street_1'] ?? '' }}
            {{ $company['address']['street_2'] ?? '' }}
            {{ $company['address']['postcode'] ?? '' }} {{ $company['address']['city'] ?? '' }}
            {{ $company['address']['state'] ?? '' }} {{ $company['address']['country'] ?? '' }}
        </div>
    @endif

    <div class="section row">
        <div class="col">
            <div class="label">Bill to</div>
            <strong>{{ $billable?->billingName() }}</strong><br>
            <span class="muted">{{ $billable?->billingEmail() }}</span><br>
            @foreach(($billable?->billingAddress() ?? []) as $line)
                {{ $line }}<br>
            @endforeach
        </div>
        <div class="col right">
            <div class="label">Issued</div>
            {{ optional($invoice->issued_at)->toDateString() }}<br>
            <div class="label" style="margin-top: 8px;">Status</div>
            {{ ucfirst($invoice->status->value) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ ucfirst($invoice->plan_tier) }} plan ({{ $invoice->interval->value }})</td>
                <td>{{ optional($invoice->period_start)->toDateString() }} — {{ optional($invoice->period_end)->toDateString() }}</td>
                <td class="right">{{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="2" class="right">Total</td>
                <td class="right">{{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
