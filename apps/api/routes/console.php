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

// Publica los eventos de dominio pendientes del outbox de cada estudio (R39).
// Frecuente para baja latencia; withoutOverlapping evita relays solapados.
Schedule::command('turnouno:despachar-outbox')->everyMinute()->withoutOverlapping();

// Reintenta las entregas de webhook fallidas de cada estudio (R40).
Schedule::command('turnouno:reintentar-webhooks')->everyFiveMinutes()->withoutOverlapping();

// Envia los mensajes encolados (y reintenta los fallidos) de cada estudio (R28).
Schedule::command('turnouno:enviar-mensajes')->everyMinute()->withoutOverlapping();
