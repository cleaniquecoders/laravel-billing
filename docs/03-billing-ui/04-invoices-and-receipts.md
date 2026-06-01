# Invoices and Receipts

Every billed period produces an **invoice** (persisted, with a stored PDF). A **receipt** is derived
on the fly from a paid invoice — there is no separate receipt record. Both render from overridable
Blade templates and are streamed through ownership-checked routes.

## How they are produced

| Document | Produced by | Stored? | Route |
|----------|-------------|---------|-------|
| Invoice | `IssueInvoice` on activate/renew | Yes — `Storage::disk(billing.invoice.disk)` | `billing.invoices.download` |
| Receipt | `GenerateReceipt` on demand | No — rendered per request | `billing.invoices.receipt` |

`IssueInvoice` allocates the next sequential number, computes the tax breakdown, renders the PDF via
DomPDF, persists the record, and fires `InvoiceIssued`. `GenerateReceipt` renders the receipt PDF
from a paid invoice and aborts otherwise.

## SST tax breakdown

When `billing.tax.enabled` is true, `IssueInvoice` computes tax from the subtotal and stores the
breakdown on the invoice:

```text
tax_cents  = round(subtotal_cents * billing.tax.rate)
total_cents = subtotal_cents + tax_cents
```

For a Pro monthly plan at 49.00 MYR with `rate = 0.08`, `label = SST`:

| Line | Amount |
|------|--------|
| Subtotal | 49.00 MYR |
| SST (8%) | 3.92 MYR |
| **Total** | **52.92 MYR** |

## Sample invoice

The bundled SST/SSM-aware template renders a tax invoice. Seller details come from
`billing.company`; the "Bill to" block from the billable's `billingName()` / `billingEmail()` /
`billingAddress()`.

[Download the sample invoice PDF](../assets/sample-invoice.pdf)

```text
INVOICE  INV-2026-000001                 Cleanique Coders Resources
                                         SSM: 201701234567
                                         SST: W10-1234-56789012
                                         billing@cleaniquecoders.com

FROM   No. 244, Jalan Sentosa, 72500 Kuala Pilah, Negeri Sembilan, Malaysia

BILL TO                                  ISSUED   2026-06-01
Demo User                                STATUS   Paid
demo@example.test
No. 1, Jalan Contoh, 50000 Kuala Lumpur, Malaysia

Description                        Qty            Amount
Pro plan (monthly)                  1          49.00 MYR
2026-06-01 — 2026-07-01
                              Subtotal          49.00 MYR
                               SST (8%)          3.92 MYR
                                 Total          52.92 MYR

Tax Invoice · Tax = SST
```

## Sample receipt

The receipt confirms payment and is derived from the paid invoice (note the `Payment method` comes
from the invoice metadata — `local` for the bundled gateway).

[Download the sample receipt PDF](../assets/sample-receipt.pdf)

```text
RECEIPT                                  Cleanique Coders Resources
Payment received                         SSM: 201701234567
52.92 MYR                                SST: W10-1234-56789012

BILLED TO
Demo User
demo@example.test

Invoice number     INV-2026-000001
Payment date       Mon, Jun 1, 2026 6:49 AM
Payment method     local
Amount paid        52.92 MYR
```

## Customizing the templates

Both templates are publishable Blade views. Publish with `--tag="laravel-billing-views"` and edit
`resources/views/vendor/billing/invoice-pdf.blade.php` and `receipt-pdf.blade.php`. The view names
are configurable via `billing.invoice.view` and `billing.invoice.receipt_view`.

## Next Steps

- [Configuration reference](../04-configuration/01-config-reference.md) — `invoice`, `tax`, and
  `company` keys.
- [The full billing cycle](../06-examples/01-full-billing-cycle.md)
