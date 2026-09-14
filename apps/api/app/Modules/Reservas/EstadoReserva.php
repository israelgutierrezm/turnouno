<?php

declare(strict_types=1);

namespace App\Modules\Reservas;

/**
 * Estado de una reserva. `EnEspera` (waitlist) se habilita en Slice 7b.
 */
enum EstadoReserva: string
{
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';
}
