<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application;

use App\Modules\Personas\Models\Perfil;
use App\Modules\Personas\Models\Persona;
use App\Modules\Personas\TipoPerfil;

/**
 * Asigna (de forma idempotente) un perfil a una persona.
 */
class AsignarPerfil
{
    public function ejecutar(Persona $persona, TipoPerfil $tipo): Perfil
    {
        return $persona->perfiles()->firstOrCreate(['tipo' => $tipo]);
    }
}
