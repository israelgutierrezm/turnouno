<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Rol de un miembro del staff en una sesion (R17): quien la imparte, quien asiste y
 * quien sustituye.
 */
enum RolSesionTenant: string
{
    case Instructor = 'instructor';
    case Asistente = 'asistente';
    case Sustituto = 'sustituto';
}
