<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\EstadoEstudio;
use App\Modules\Tenancy\EstadoFacturacion;
use App\Modules\Tenancy\Exceptions\SlugNoDisponible;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Crea el registro central de un estudio en estado `provisioning`. El slug se
 * reserva mediante el índice único (`estudios.slug`): dos registros concurrentes
 * con el mismo slug no pueden coexistir; el perdedor recibe SLUG_TAKEN. No crea
 * la BD del tenant (eso lo hace {@see AprovisionarEstudio}).
 *
 * @phpstan-type DatosRegistro array{nombre: string, slug: string, contacto_nombre: string, contacto_email: string, contacto_telefono?: string|null, pais?: string|null, ciudad?: string|null, zona_horaria?: string|null}
 */
class RegistrarEstudio
{
    /**
     * @param  DatosRegistro  $datos
     */
    public function ejecutar(array $datos): Estudio
    {
        $slug = Str::slug($datos['slug']);
        $driver = (string) config('turnouno.tenant_db_driver', 'sqlite');

        $dbDatabase = $driver === 'sqlite'
            ? $slug.'_'.Str::lower(Str::random(8)).'.sqlite'
            : 'tenant_'.str_replace('-', '_', $slug).'_'.Str::lower(Str::random(8));

        try {
            return Estudio::create([
                'nombre' => $datos['nombre'],
                'slug' => $slug,
                'estado' => EstadoEstudio::Provisioning->value,
                'estado_facturacion' => EstadoFacturacion::Trial->value,
                'contacto_nombre' => $datos['contacto_nombre'],
                'contacto_email' => $datos['contacto_email'],
                'contacto_telefono' => $datos['contacto_telefono'] ?? null,
                'pais' => $datos['pais'] ?? null,
                'ciudad' => $datos['ciudad'] ?? null,
                'zona_horaria' => $datos['zona_horaria'] ?? 'America/Mexico_City',
                'db_driver' => $driver,
                'db_database' => $dbDatabase,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new SlugNoDisponible('El slug ya está en uso.');
        }
    }
}
