# Workbench Preview

The package ships a Testbench workbench so you can click through the real Flux UI — the full
**subscribe → pay → invoice → receipt** flow — without wiring it into a host app. The screenshots in
[Billing UI → Overview](../03-billing-ui/01-overview.md) come from this workbench.

## Prerequisites

- Node.js and npm (for the Vite + Tailwind build that compiles the Flux styles).
- The dev dependencies are already declared (`livewire/livewire`, `livewire/flux`).

## Run it

```bash
npm install
npm run dev                                # Vite + Tailwind — keep this running
php vendor/bin/testbench workbench:build   # once: create the sqlite db + run migrations
php vendor/bin/testbench serve             # http://127.0.0.1:8000
```

Open the root URL — it auto-logs in a demo billable and lands on `/billing/plans`.

## What the workbench configures

`WorkbenchServiceProvider` mirrors what a host app sets in `config/billing.php`:

| Setting | Demo value |
|---------|-----------|
| Plan store | `config` |
| Plans | Free / Pro (49.00) / Team (99.00), monthly + annual |
| Gateway | `local`, with the dev "Approve" page (`auto = false`) |
| Tax | SST, 8% |
| Company | Cleanique Coders Resources (SSM/SST sample) |
| Layout | Flux-enabled demo layout (`@vite` + `@fluxAppearance`/`@fluxScripts`) |

Relevant files: `testbench.yaml`, `workbench/app/Models/User.php`,
`workbench/app/Providers/WorkbenchServiceProvider.php`, `workbench/routes/web.php`, and
`workbench/resources/`.

> **Note**: To skip the dev checkout page and activate instantly (handy for quick demos), set
> `gateways.local.auto` to `true` in the workbench provider.

## Next Steps

- [Testing](02-testing.md)
- [Billing UI overview](../03-billing-ui/01-overview.md)
