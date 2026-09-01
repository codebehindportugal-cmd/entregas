<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Renovacao das subscricoes auto-renovaveis: cria a encomenda nova no dia da
// ultima entrega do ciclo, para depois se enviar o link de pagamento ao cliente.
Schedule::command('subscricoes:renovar')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();
