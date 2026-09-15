<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Estado de una sesión de la agenda tenant-local.
 */
enum EstadoSesionTenant: string
{
    case Programada = 'programada';
    case Cancelada = 'cancelada';
}
