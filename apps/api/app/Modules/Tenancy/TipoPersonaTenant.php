<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Tipo de persona operativa tenant-local. `Miembro` (alumno) es lo que cuenta para
 * la facturación por alumnos activos; instructor/staff no.
 */
enum TipoPersonaTenant: string
{
    case Miembro = 'miembro';
    case Instructor = 'instructor';
    case Staff = 'staff';
}
