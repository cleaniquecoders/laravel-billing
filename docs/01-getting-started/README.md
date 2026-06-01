# Getting Started

This section takes you from installation to a billable model with a working plan matrix.

## Table of Contents

### [1. Installation](01-installation.md)

Install the package, publish config and migrations, and (optionally) the billing UI dependencies.

### [2. Make a Model Billable](02-make-billable.md)

Implement the `Billable` contract and use the `HasSubscriptions` trait on `User`, `Team`, or any
model.

### [3. Plans](03-plans.md)

Define plans in config or the database, and read them through the `PlanRepository`.

## Related Documentation

- [Architecture](../02-architecture/README.md) — how the engine is put together.
- [Configuration](../04-configuration/README.md) — full config reference.
