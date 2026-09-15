<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Http\Requests\CrearMiembroRequest;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\TipoPersonaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alta y listado de personas (alumnos/instructores) del estudio. Opera SIEMPRE
 * sobre la BD del tenant ya resuelto (ResolverEstudio + AutenticarTenant), así que
 * un estudio nunca ve ni crea personas de otro. Listado paginado/limitado.
 */
class MiembrosTenantController
{
    private const LIMITE = 100;

    public function index(Request $request): JsonResponse
    {
        $tipo = (string) $request->query('tipo', TipoPersonaTenant::Miembro->value);

        $personas = PersonaTenant::query()
            ->where('tipo', $tipo)
            ->where('archivado', false)
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get();

        return response()->json([
            'data' => $personas->map(fn (PersonaTenant $persona): array => $this->presentar($persona))->all(),
        ]);
    }

    public function store(CrearMiembroRequest $request): JsonResponse
    {
        $persona = PersonaTenant::query()->create([
            'nombre' => (string) $request->validated('nombre'),
            'apellidos' => $request->validated('apellidos'),
            'email' => $request->validated('email'),
            'tipo' => (string) $request->validated('tipo', TipoPersonaTenant::Miembro->value),
            'activo' => true,
            'es_facturable' => (bool) $request->validated('es_facturable', true),
            'archivado' => false,
        ]);

        return response()->json(['data' => $this->presentar($persona)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(PersonaTenant $persona): array
    {
        return [
            'id' => $persona->ulid,
            'nombre' => $persona->nombre,
            'apellidos' => $persona->apellidos,
            'email' => $persona->email,
            'tipo' => $persona->tipo->value,
            'activo' => $persona->activo,
            'es_facturable' => $persona->es_facturable,
        ];
    }
}
