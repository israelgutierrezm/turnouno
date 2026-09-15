<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Application;

use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Creditos\Application\ConfirmarRetencion;
use App\Modules\Creditos\Application\PerderRetencion;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Exceptions\ReservaNoConfirmada;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Registra (o re-marca) la asistencia de una reserva confirmada y LIQUIDA su
 * retención exactamente una vez (F-02): `presente` consume el crédito (servicio
 * prestado), `ausente` lo pierde (no-show). Las liquidaciones sólo actúan sobre
 * una retención activa, de modo que re-marcar es idempotente y no cobra dos veces.
 * Un derecho ilimitado no tiene retención y sólo registra la asistencia.
 */
class MarcarAsistencia
{
    public function __construct(
        private readonly ConfirmarRetencion $confirmar,
        private readonly PerderRetencion $perder,
    ) {}

    public function ejecutar(Reserva $reserva, EstadoAsistencia $estado): Asistencia
    {
        if ($reserva->estado !== EstadoReserva::Confirmada) {
            throw new ReservaNoConfirmada('Solo se registra asistencia de reservas confirmadas.');
        }

        return DB::transaction(function () use ($reserva, $estado): Asistencia {
            $asistencia = Asistencia::updateOrCreate(
                ['reserva_id' => $reserva->id],
                ['estado' => $estado->value, 'registrada_en' => now()],
            );

            $retencion = $reserva->retencion;

            if ($retencion !== null) {
                match ($estado) {
                    EstadoAsistencia::Presente => $this->confirmar->ejecutar($retencion),
                    EstadoAsistencia::Ausente => $this->perder->ejecutar($retencion),
                };
            }

            return $asistencia;
        });
    }
}
