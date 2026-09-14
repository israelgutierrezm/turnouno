<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application;

use App\Modules\Hogares\Models\Hogar;
use App\Modules\Personas\Models\Persona;
use App\Modules\Personas\TipoPerfil;

class CrearPersona
{
    /**
     * @param  list<string>  $perfiles  valores de TipoPerfil
     */
    public function ejecutar(
        string $nombre,
        ?string $apellidos = null,
        ?string $email = null,
        ?string $fechaNacimiento = null,
        ?Hogar $hogar = null,
        array $perfiles = [],
    ): Persona {
        $persona = Persona::create([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'email' => $email,
            'fecha_nacimiento' => $fechaNacimiento,
            'hogar_id' => $hogar?->id,
        ]);

        foreach ($perfiles as $tipo) {
            $persona->perfiles()->firstOrCreate(['tipo' => TipoPerfil::from($tipo)]);
        }

        return $persona;
    }
}
