<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Models\EventoOutboxTenant;
use Illuminate\Support\Carbon;

/**
 * Cara de ESCRITURA del outbox (R39): agrega un evento de dominio a `eventos_outbox`
 * en la BD del tenant. Debe llamarse DENTRO de la transaccion que cambia el estado,
 * para que el evento y el cambio sean atomicos (patron outbox transaccional). El
 * relay {@see DespacharOutboxTenant} lo publica despues.
 */
class RegistrarEventoTenant
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function registrar(string $tipo, string $agregadoTipo, ?string $agregadoId, array $payload = []): EventoOutboxTenant
    {
        return EventoOutboxTenant::query()->create([
            'tipo' => $tipo,
            'agregado_tipo' => $agregadoTipo,
            'agregado_id' => $agregadoId,
            'payload' => $payload,
            'correlation_id' => request()->header('X-Correlation-ID'),
            'ocurrido_en' => Carbon::now(),
            'publicado_en' => null,
            'intentos' => 0,
        ]);
    }
}
