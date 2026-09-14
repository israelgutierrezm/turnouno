<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Application;

use App\Modules\Organizaciones\Models\Organizacion;
use App\Modules\Organizaciones\Models\Sucursal;
use Illuminate\Support\Str;

class CrearSucursal
{
    public function ejecutar(
        Organizacion $organizacion,
        string $nombre,
        ?string $zonaHoraria = null,
        ?string $slug = null,
    ): Sucursal {
        // La sucursal cuelga de la marca por defecto de la organización.
        $marca = $organizacion->marcas()->firstOrFail();

        return Sucursal::create([
            'marca_id' => $marca->id,
            'nombre' => $nombre,
            'slug' => $slug ?? Str::slug($nombre).'-'.Str::lower(Str::random(4)),
            'zona_horaria' => $zonaHoraria,
            'estado' => 'activa',
        ]);
    }
}
