<?php

declare(strict_types=1);

namespace App\Modules\Agenda;

/**
 * Rol con el que una persona (staff) queda asignada a una sesión.
 */
enum RolSesion: string
{
    case Instructor = 'instructor';
    case Asistente = 'asistente';
}
