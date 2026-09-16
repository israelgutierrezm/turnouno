<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Almacenamiento de archivos con aislamiento por estudio: todo cuelga de
 * `estudios/{id}/...`, de modo que los archivos de un estudio nunca se mezclan con
 * los de otro. Usa el disco por defecto (S3-compatible en produccion).
 */
class AlmacenamientoTenant
{
    public function __construct(private readonly GestorDeConexionTenant $gestor) {}

    public function disco(): Filesystem
    {
        return Storage::disk((string) config('filesystems.default'));
    }

    /**
     * Ruta namespaced por el estudio activo: `estudios/{id}/{sub}`.
     */
    public function ruta(string $sub = ''): string
    {
        $estudio = $this->gestor->actual();
        $base = 'estudios/'.($estudio !== null ? (string) $estudio->id : 'global');

        return $sub === '' ? $base : $base.'/'.ltrim($sub, '/');
    }

    /**
     * Guarda un archivo subido bajo el espacio del estudio y devuelve su ruta.
     */
    public function guardar(UploadedFile $archivo, string $sub, string $nombre): string
    {
        return (string) $this->disco()->putFileAs($this->ruta($sub), $archivo, $nombre);
    }

    public function contenido(string $ruta): string
    {
        return (string) $this->disco()->get($ruta);
    }

    public function eliminar(string $ruta): void
    {
        $this->disco()->delete($ruta);
    }
}
