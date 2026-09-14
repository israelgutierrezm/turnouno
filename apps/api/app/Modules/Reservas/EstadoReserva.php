<?php

declare(strict_types=1);

namespace App\Modules\Reservas;

/**
 * Estado de una reserva. `EnEspera` = lista de espera (waitlist): sin retención de
 * crédito hasta que se promueve a `Confirmada` al liberarse un cupo.
 */
enum EstadoReserva: string
{
    case Confirmada = 'confirmada';
    case EnEspera = 'en_espera';
    case Cancelada = 'cancelada';
}
