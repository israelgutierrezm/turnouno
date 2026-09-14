<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Application;

use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Exceptions\ReservaNoConfirmada;
use App\Modules\Reservas\Models\Reserva;

/**
 * Registra (o re-marca) la asistencia de una reserva confirmada. Idempotente por
 * reserva: re-marcar solo actualiza el estado y la hora.
 */
class MarcarAsistencia
{
    public function ejecutar(Reserva $reserva, EstadoAsistencia $estado): Asistencia
    {
        if ($reserva->estado !== EstadoReserva::Confirmada) {
            throw new ReservaNoConfirmada('Solo se registra asistencia de reservas confirmadas.');
        }

        return Asistencia::updateOrCreate(
            ['reserva_id' => $reserva->id],
            [
                'estado' => $estado->value,
                'registrada_en' => now(),
            ],
        );
    }
}
