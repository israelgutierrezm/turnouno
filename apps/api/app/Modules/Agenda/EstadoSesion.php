<?php

declare(strict_types=1);

namespace App\Modules\Agenda;

/**
 * Estado del ciclo de vida de una sesión. Booking (Slice 7) rechazará reservas
 * sobre sesiones que no estén `Programada`.
 */
enum EstadoSesion: string
{
    case Programada = 'programada';
    case Cancelada = 'cancelada';
    case Finalizada = 'finalizada';
}
