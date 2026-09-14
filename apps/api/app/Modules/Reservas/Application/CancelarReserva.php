<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Application;

use App\Modules\Creditos\Application\ConfirmarRetencion;
use App\Modules\Creditos\Application\LiberarRetencion;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Cancela una reserva aplicando la política de cancelación por hold:
 * a tiempo (a más de `horasLimite` del inicio) libera la retención y el crédito
 * vuelve; tarde, la confirma (penaliza) y el crédito se consume.
 *
 * Idempotente: cancelar una reserva ya cancelada no hace nada. La promoción de la
 * lista de espera llegará en Slice 7b.
 */
class CancelarReserva
{
    public function __construct(
        private readonly ConfirmarRetencion $confirmar,
        private readonly LiberarRetencion $liberar,
    ) {}

    public function ejecutar(Reserva $reserva, int $horasLimite = 6): Reserva
    {
        return DB::transaction(function () use ($reserva, $horasLimite): Reserva {
            $bloqueada = Reserva::query()->whereKey($reserva->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado !== EstadoReserva::Confirmada) {
                return $bloqueada;
            }

            $retencion = $bloqueada->retencion;
            if ($retencion !== null) {
                $momentoLimite = $bloqueada->sesion->inicia_en->copy()->subHours($horasLimite);

                if (now()->lessThanOrEqualTo($momentoLimite)) {
                    $this->liberar->ejecutar($retencion);
                } else {
                    $this->confirmar->ejecutar($retencion);
                }
            }

            $bloqueada->update(['estado' => EstadoReserva::Cancelada->value]);

            return $bloqueada;
        });
    }
}
