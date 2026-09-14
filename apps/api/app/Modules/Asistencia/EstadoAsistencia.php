<?php

declare(strict_types=1);

namespace App\Modules\Asistencia;

/**
 * Resultado del check-in de una reserva confirmada.
 */
enum EstadoAsistencia: string
{
    case Presente = 'presente';
    case Ausente = 'ausente';
}
