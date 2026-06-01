# Testing

The package is tested with Pest on Orchestra Testbench. Livewire components are tested headlessly
with `Livewire::test()` — no browser required.

## Commands

```bash
composer test       # Pest
composer analyse    # PHPStan / Larastan (level 5)
vendor/bin/pint     # code style (run with --test to check only)
```

## What is covered

| Suite | Verifies |
|-------|----------|
| `SubscribeFlowTest` | End-to-end checkout through the local gateway (auto) |
| `WebhookTest` | Activate/renew/cancel, payment success/failure, replay guard |
| `InvoiceTest` | Sequential numbering, PDF storage, `InvoiceIssued` |
| `InvoiceTaxTest` | SST tax math (subtotal / tax / total) |
| `Livewire/PlansTest` | Subscribe action + current-tier badge |
| `Livewire/BillingPortalTest` | Invoice scoping, cancel/resume, detail panel |
| `InvoiceDownloadTest` | Download/receipt streaming + ownership (403 / 404) |

## Hermetic environment

`tests/TestCase.php` pins in-memory drivers (`cache`, `session` → `array`; `queue` → `sync`) and an
in-memory SQLite connection, so the suite is independent of any local `.env` (including the
workbench skeleton's). It also registers Livewire and Flux providers when present so component views
render.

## Next Steps

- [Workbench preview](01-workbench-preview.md)
- [Architecture](../02-architecture/README.md)
