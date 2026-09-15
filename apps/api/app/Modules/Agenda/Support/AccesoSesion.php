<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Support;

use App\Modules\Agenda\Models\AsignacionSesion;
use App\Modules\Agenda\Models\Sesion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Decide si el usuario autenticado puede operar (ver roster / marcar asistencia
 * de) una sesión concreta. El personal con alcance amplio —gestiona la agenda o
 * crea reservas: propietario/gerente/recepcionista— opera cualquier sesión; el
 * instructor solo las sesiones a las que está asignado (F-09/SEC-05).
 */
class AccesoSesion
{
    public function puedeOperar(Sesion $sesion): bool
    {
        if (Gate::any(['agenda.gestionar', 'reservas.crear'])) {
            return true;
        }

        return AsignacionSesion::query()
            ->where('sesion_id', $sesion->id)
            ->whereHas('persona', function (Builder $consulta): void {
                $consulta->where('user_id', Auth::id());
            })
            ->exists();
    }
}
