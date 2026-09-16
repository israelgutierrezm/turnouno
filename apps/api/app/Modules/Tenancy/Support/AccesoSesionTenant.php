<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\SesionTenant;
use App\Modules\Tenancy\Models\Usuario;

/**
 * Politica de acceso a una sesion segun el usuario tenant-local. Un instructor solo
 * opera (ve roster, marca asistencia) sobre SUS sesiones asignadas; el resto del
 * staff (propietario/admin/recepcionista) opera sobre todas. Privacidad: un
 * instructor no debe ver los datos de las clases de otros.
 */
class AccesoSesionTenant
{
    public function puedeOperar(SesionTenant $sesion, ?Usuario $usuario): bool
    {
        if (! $usuario instanceof Usuario) {
            return false;
        }

        if ((string) $usuario->rol !== 'instructor') {
            return true;
        }

        return $sesion->instructor_id !== null && (int) $sesion->instructor_id === (int) $usuario->getKey();
    }

    /**
     * ¿El usuario es un instructor (cuyo alcance se limita a sus sesiones)?
     */
    public function esInstructorAcotado(?Usuario $usuario): bool
    {
        return $usuario instanceof Usuario && (string) $usuario->rol === 'instructor';
    }
}
