<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\InscribirEnGrupoTenant;
use App\Modules\Tenancy\Models\GrupoTenant;
use App\Modules\Tenancy\Models\InscripcionGrupoTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\PlantillaHorarioTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grupos / cursos del estudio (R25): un grupo sigue una plantilla de horario (serie);
 * inscribir a una persona la auto-reserva en las ocurrencias futuras. Opera SIEMPRE
 * sobre la BD del estudio resuelto.
 */
class GruposTenantController
{
    public function __construct(private readonly InscribirEnGrupoTenant $inscribir) {}

    public function index(): JsonResponse
    {
        $grupos = GrupoTenant::query()->with('plantilla.oferta')->withCount('inscripciones')->orderByDesc('id')->get();

        return response()->json([
            'data' => $grupos->map(fn (GrupoTenant $g): array => [
                'id' => $g->ulid,
                'nombre' => $g->nombre,
                'oferta' => $g->plantilla?->oferta?->nombre,
                'inscritos' => (int) ($g->getAttribute('inscripciones_count') ?? 0),
                'activo' => $g->activo,
            ])->all(),
        ]);
    }

    public function crear(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'plantilla_id' => ['required', 'string'],
        ]);

        $plantilla = PlantillaHorarioTenant::query()->where('ulid', $validado['plantilla_id'])->firstOrFail();

        $grupo = GrupoTenant::query()->create([
            'nombre' => $validado['nombre'],
            'plantilla_id' => $plantilla->getKey(),
            'activo' => true,
        ]);

        return response()->json(['data' => ['id' => $grupo->ulid, 'nombre' => $grupo->nombre]], 201);
    }

    public function inscribir(Request $request): JsonResponse
    {
        $grupo = GrupoTenant::query()->where('ulid', (string) $request->route('grupo'))->firstOrFail();
        $validado = $request->validate(['persona_id' => ['required', 'string']]);
        $persona = PersonaTenant::query()->where('ulid', $validado['persona_id'])->firstOrFail();

        $resultado = $this->inscribir->inscribir($grupo, $persona);

        return response()->json(['data' => [
            'grupo' => $grupo->ulid,
            'persona' => $persona->ulid,
            'reservadas' => $resultado['reservadas'],
        ]], 201);
    }

    public function inscripciones(Request $request): JsonResponse
    {
        $grupo = GrupoTenant::query()->where('ulid', (string) $request->route('grupo'))->firstOrFail();

        $inscripciones = $grupo->inscripciones()->with('persona')->get();

        return response()->json([
            'data' => $inscripciones->map(fn (InscripcionGrupoTenant $i): array => [
                'id' => $i->ulid,
                'persona' => $i->persona?->nombre,
                'activo' => $i->activo,
            ])->all(),
        ]);
    }
}
