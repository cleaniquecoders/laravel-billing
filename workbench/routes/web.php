<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

/*
| Workbench entry point: auto-logs in the demo billable (creating it if the
| seeder hasn't run) and lands on the billing portal. Lets you `testbench serve`
| and explore the subscribe → pay → invoice → receipt flow without auth setup.
*/

Route::get('/', function () {
    $user = User::query()->firstOr(fn () => User::query()->create([
        'name' => 'Demo User',
        'email' => 'demo@example.test',
        'password' => bcrypt('password'),
    ]));

    Auth::login($user);

    return redirect()->route('billing.plans');
});
