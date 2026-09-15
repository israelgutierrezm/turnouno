<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Application;

use App\Modules\Agenda\Models\Sesion;
use App\Modules\Creditos\Application\LiberarRetencion;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Models\Reserva;

/**
 * Cancela todas las reservas activas (confirmadas + en espera) de una sesión y
 * libera sus holds. Una cancelación del negocio no penaliza al miembro: el
 * crédito retenido vuelve a estar disponible (F-06). Idempotente: reservas ya
 * canceladas se ignoran y `LiberarRetencion` sólo actúa sobre holds activos.
 */
class CancelarReservasDeSesion
{
    public function __construct(private readonly LiberarRetencion $liberar) {}

    public function ejecutar(Sesion $sesion): void
    {
        $reservas = Reserva::query()
            ->where('sesion_id', $sesion->id)
            ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::EnEspera->value])
            ->with('retencion')
            ->get();

        foreach ($reservas as $reserva) {
            if ($reserva->retencion !== null) {
                $this->liberar->ejecutar($reserva->retencion);
            }

            $reserva->update(['estado' => EstadoReserva::Cancelada->value]);
        }
    }
}
