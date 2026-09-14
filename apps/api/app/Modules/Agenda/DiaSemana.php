<?php

declare(strict_types=1);

namespace App\Modules\Agenda;

/**
 * Día de la semana según ISO-8601 (lunes = 1 … domingo = 7), para alinear con
 * `Carbon::dayOfWeekIso` al materializar sesiones desde las reglas de recurrencia.
 */
enum DiaSemana: int
{
    case Lunes = 1;
    case Martes = 2;
    case Miercoles = 3;
    case Jueves = 4;
    case Viernes = 5;
    case Sabado = 6;
    case Domingo = 7;
}
