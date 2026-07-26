<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Retention periods are still PROPOSED. Enable only after legal review marks
 * every enforced period DECIDED and a dedicated maintenance runtime is wired.
 *
 * \Illuminate\Support\Facades\Schedule::command('elyo:enforce-retention --execute')
 *     ->daily()
 *     ->withoutOverlapping();
 */
