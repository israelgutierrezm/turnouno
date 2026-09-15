<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reinicia/renueva a diario los ciclos vencidos de los derechos recurrentes.
Schedule::command('entitlements:generar-ciclos')->dailyAt('00:15');
