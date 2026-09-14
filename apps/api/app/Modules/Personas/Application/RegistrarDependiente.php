<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application;

use App\Modules\Personas\Models\Persona;
use App\Modules\Personas\Models\Tutela;
use App\Modules\Personas\TipoPerfil;

/**
 * Registra un dependiente bajo un tutor: crea la persona dependiente (que hereda
 * el hogar del tutor), le da el perfil Miembro, asegura el perfil Tutor del
 * tutor y crea la relación de tutela. Modela comprador != participante.
 */
class RegistrarDependiente
{
    public function ejecutar(
        Persona $tutor,
        string $nombre,
        ?string $apellidos = null,
        ?string $fechaNacimiento = null,
        ?string $parentesco = null,
    ): Persona {
        $tutor->perfiles()->firstOrCreate(['tipo' => TipoPerfil::Tutor]);

        $dependiente = Persona::create([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'fecha_nacimiento' => $fechaNacimiento,
            'hogar_id' => $tutor->hogar_id,
        ]);
        $dependiente->perfiles()->firstOrCreate(['tipo' => TipoPerfil::Miembro]);

        Tutela::create([
            'tutor_id' => $tutor->id,
            'dependiente_id' => $dependiente->id,
            'parentesco' => $parentesco,
        ]);

        return $dependiente;
    }
}
