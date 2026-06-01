<?php

namespace CleaniqueCoders\LaravelBilling\Concerns;

/**
 * Lightweight, dependency-free created_by / updated_by stamping. Opt-in via
 * config('billing.audit'). UUID generation is delegated to Traitify's
 * InteractsWithUuid; this only covers the audit columns Traitify does not.
 */
trait InteractsWithAudit
{
    public static function bootInteractsWithAudit(): void
    {
        static::creating(function ($model): void {
            if (config('billing.audit', false) && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model): void {
            if (config('billing.audit', false)) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
