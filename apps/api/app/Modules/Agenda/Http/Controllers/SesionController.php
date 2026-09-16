<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Agenda\Application\CancelarSesion;
use App\Modules\Agenda\Application\CrearSesionUnica;
use App\Modules\Agenda\Http\Requests\CrearSesionUnicaRequest;
use App\Modules\Agenda\Http\SesionPresenter;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Models\Recurso;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Agenda de una sucursal: listar sesiones, crear sesiones únicas y cancelarlas.
 */
class SesionController
{
    public function index(Request $request, Sucursal $sucursal): JsonResponse
    {
        Gate::authorize('agenda.ver');

        $zona = $this->zonaDe($sucursal);
        $consulta = Sesion::query()->where('sucursal_id', $sucursal->id);

        $desde = $request->query('desde');
        if (is_string($desde) && $desde !== '') {
            $consulta->where('inicia_en', '>=', CarbonImmutable::parse($desde, $zona)->utc());
        }

        $hasta = $request->query('hasta');
        if (is_string($hasta) && $hasta !== '') {
            $consulta->where('inicia_en', '<', CarbonImmutable::parse($hasta, $zona)->addDay()->utc());
        }

        $sesiones = $consulta
            ->with(['oferta', 'asignaciones.persona'])
            ->orderBy('inicia_en')
            ->get();

        return response()->json([
            'data' => $sesiones->map(static fn (Sesion $sesion): array => SesionPresenter::datos($sesion))->all(),
        ]);
    }

    public function store(CrearSesionUnicaRequest $request, Sucursal $sucursal, CrearSesionUnica $crear): JsonResponse
    {
        Gate::authorize('agenda.gestionar');

        $oferta = Oferta::query()
            ->where('ulid', (string) $request->validated('oferta_id'))
            ->firstOrFail();

        $recurso = $this->resolverRecurso($request->validated('recurso_id'));
        $capacidad = $request->validated('capacidad');

        $sesion = $crear->ejecutar(
            $oferta,
            $sucursal,
            [
                'inicia_en_local' => (string) $request->validated('inicia_en_local'),
                'duracion_minutos' => (int) $request->validated('duracion_minutos'),
                'capacidad' => is_numeric($capacidad) ? (int) $capacidad : null,
            ],
            $recurso,
        );

        return response()->json(['data' => SesionPresenter::datos($sesion)], 201);
    }

    public function cancelar(Sesion $sesion, CancelarSesion $cancelar): JsonResponse
    {
        Gate::authorize('agenda.gestionar');

        $cancelar->ejecutar($sesion);

        return response()->json(['data' => SesionPresenter::datos($sesion)]);
    }

    private function zonaDe(Sucursal $sucursal): string
    {
        // `zona_horaria` es nullable en el esquema legacy (aunque el analizador la vea
        // como string por la colisión con la tabla tenant): `?:` cubre null y vacío.
        return $sucursal->zona_horaria ?: 'UTC';
    }

    private function resolverRecurso(mixed $ulid): ?Recurso
    {
        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        return Recurso::query()->where('ulid', $ulid)->firstOrFail();
    }
}
