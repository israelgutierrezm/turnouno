<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Application;

use App\Modules\Agenda\Models\AsignacionSesion;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Agenda\RolSesion;
use App\Modules\Personas\Models\Persona;

/**
 * Asigna una persona (staff) a una sesión con un rol. Idempotente por
 * `(sesion, persona)`: reasignar solo actualiza el rol.
 */
class AsignarPersonalSesion
{
    public function ejecutar(Sesion $sesion, Persona $persona, RolSesion $rol = RolSesion::Instructor): AsignacionSesion
    {
        return AsignacionSesion::updateOrCreate(
            [
                'sesion_id' => $sesion->id,
                'persona_id' => $persona->id,
            ],
            [
                'rol' => $rol->value,
            ],
        );
    }
}
