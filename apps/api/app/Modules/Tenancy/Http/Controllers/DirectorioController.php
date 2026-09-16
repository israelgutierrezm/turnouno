<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\EstadoEstudio;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Directorio público de estudios: solo publicados, no privados y operativos.
 * Expone únicamente datos públicos (slug, nombre, logo, ciudad/país); nunca IDs
 * internos, usuarios ni datos privados. Paginado/limitado y con throttle en la
 * ruta para mitigar scraping.
 */
class DirectorioController
{
    private const LIMITE = 50;

    public function __construct(private readonly GestorDeConexionTenant $gestor) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $pais = trim((string) $request->query('pais', ''));

        $estudios = Estudio::query()
            ->where('publicado', true)
            ->where('privado', false)
            ->whereIn('estado', [EstadoEstudio::Trialing->value, EstadoEstudio::Active->value])
            ->when($q !== '', function (Builder $consulta) use ($q): void {
                $consulta->where('nombre', 'like', '%'.$q.'%');
            })
            ->when($pais !== '', function (Builder $consulta) use ($pais): void {
                $consulta->where('pais', mb_strtoupper($pais));
            })
            ->orderBy('nombre')
            ->limit(self::LIMITE)
            ->get()
            // Excluye estudios "fantasma": publicados pero cuya BD no existe (p. ej.
            // aprovisionamiento incompleto). No se listan cosas que no se pueden abrir.
            ->filter(fn (Estudio $estudio): bool => $this->gestor->baseDeDatosExiste($estudio))
            ->values();

        return response()->json([
            'data' => $estudios->map(static fn (Estudio $estudio): array => [
                'slug' => $estudio->slug,
                'nombre' => $estudio->nombre,
                'logo_url' => $estudio->logo_url,
                'ciudad' => $estudio->ciudad,
                'pais' => $estudio->pais,
                'url' => url('/app/'.$estudio->slug),
            ])->all(),
        ]);
    }
}
