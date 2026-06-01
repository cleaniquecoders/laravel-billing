<?php

use Illuminate\Database\Migrations\Migration;

/*
| Runs the package's publishable migration stubs for the workbench demo. In a
| host app these are published (stub → timestamped .php); here we include them
| directly so `testbench workbench:build` provisions the billing tables.
*/

return new class extends Migration
{
    /** @var array<int,string> */
    protected array $stubs = [
        'create_plans_table',
        'create_subscriptions_table',
        'create_invoices_table',
        'create_usage_counters_table',
    ];

    public function up(): void
    {
        foreach ($this->stubs as $stub) {
            (include $this->path($stub))->up();
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->stubs) as $stub) {
            (include $this->path($stub))->down();
        }
    }

    protected function path(string $stub): string
    {
        return dirname(__DIR__, 3).'/database/migrations/'.$stub.'.php.stub';
    }
};
