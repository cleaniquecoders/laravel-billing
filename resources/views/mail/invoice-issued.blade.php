@component('mail::message')
# Invoice {{ $invoice->number }}

An invoice has been issued for your subscription.

- **Number:** {{ $invoice->number }}
- **Plan:** {{ $invoice->plan_tier }}
- **Period:** {{ optional($invoice->period_start)->toDateString() }} — {{ optional($invoice->period_end)->toDateString() }}
- **Total:** {{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}

Thank you.

@endcomponent
