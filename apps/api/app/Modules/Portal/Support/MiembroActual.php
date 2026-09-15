<?php

declare(strict_types=1);

namespace App\Modules\Portal\Support;

use App\Modules\Personas\Models\Persona;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve la Persona del usuario autenticado (su perfil de miembro en el tenant).
 * Todas las acciones del portal operan sobre esta persona: el miembro solo actúa
 * sobre sí mismo.
 */
class MiembroActual
{
    public function persona(): Persona
    {
        $usuarioId = Auth::id();

        $persona = Persona::query()->where('user_id', $usuarioId)->first();
        abort_if($persona === null, 403, 'No hay un perfil de miembro para este usuario.');

        return $persona;
    }
}
