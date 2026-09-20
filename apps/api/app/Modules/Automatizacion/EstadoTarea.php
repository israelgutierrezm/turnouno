<?php

declare(strict_types=1);

namespace App\Modules\Automatizacion;

/**
 * Estado de una tarea de seguimiento (R16): pendiente hasta que el staff la completa.
 */
enum EstadoTarea: string
{
    case Pendiente = 'pendiente';
    case Completada = 'completada';
}
