<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Application;

use App\Modules\Organizaciones\Models\Marca;
use App\Modules\Organizaciones\Models\Organizacion;
use Illuminate\Support\Str;

/**
 * Crea una organización y su marca por defecto.
 *
 * La jerarquía Organización → Marca → Sucursal se conserva completa aunque en
 * el MVP la marca sea transparente: así no forzamos gestión de marcas todavía
 * y evitamos rehacer el esquema cuando aparezcan marcas múltiples.
 */
class CrearOrganizacion
{
    public function ejecutar(string $nombre, ?string $slug = null): Organizacion
    {
        $slug ??= Str::slug($nombre).'-'.Str::lower(Str::random(4));

        $organizacion = Organizacion::create(['nombre' => $nombre, 'slug' => $slug]);

        Marca::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => $nombre,
            'slug' => $slug.'-marca',
        ]);

        return $organizacion;
    }
}
