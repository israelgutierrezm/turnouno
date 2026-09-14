<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Application;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Models\Sesion;

/**
 * Marca una sesión como cancelada. Booking (Slice 7) rechazará reservas sobre
 * sesiones canceladas; la liberación de reservas existentes se resolverá ahí.
 */
class CancelarSesion
{
    public function ejecutar(Sesion $sesion): Sesion
    {
        $sesion->update(['estado' => EstadoSesion::Cancelada->value]);

        return $sesion;
    }
}
