<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Estado de validación de un documento cargado por una persona del estudio.
 */
enum EstadoDocumento: string
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
}
