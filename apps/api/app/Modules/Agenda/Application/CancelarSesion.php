<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Application;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Reservas\Application\CancelarReservasDeSesion;
use Illuminate\Support\Facades\DB;

/**
 * Cancela una sesión como operación agregada y transaccional: marca la sesión
 * cancelada y, en la misma transacción, cancela sus reservas activas y libera los
 * holds asociados (F-06), de modo que no queden reservas confirmadas ni créditos
 * retenidos sobre una sesión que ya no ocurrirá. Idempotente.
 */
class CancelarSesion
{
    public function __construct(private readonly CancelarReservasDeSesion $cancelarReservas) {}

    public function ejecutar(Sesion $sesion): Sesion
    {
        DB::transaction(function () use ($sesion): void {
            // Lock para serializar cancelaciones/reservas concurrentes sobre la sesión.
            Sesion::query()->whereKey($sesion->getKey())->lockForUpdate()->firstOrFail();

            $sesion->update(['estado' => EstadoSesion::Cancelada->value]);

            $this->cancelarReservas->ejecutar($sesion);
        });

        return $sesion;
    }
}
