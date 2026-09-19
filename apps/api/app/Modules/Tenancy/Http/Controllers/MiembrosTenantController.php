<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Http\Requests\CrearMiembroRequest;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
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
            ->with('sucursal')
            ->where('tipo', $tipo)
            ->where('archivado', false)
            // Filtro opcional por sucursal de casa (R18).
            ->when($this->sucursalIdDe((string) $request->query('sucursal_id', '')), fn ($q, int $id) => $q->where('sucursal_id', $id))
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
            'segundo_nombre' => $request->validated('segundo_nombre'),
            'primer_apellido' => $request->validated('primer_apellido'),
            'segundo_apellido' => $request->validated('segundo_apellido'),
            'email' => $request->validated('email'),
            'tipo' => (string) $request->validated('tipo', TipoPersonaTenant::Miembro->value),
            'activo' => true,
            'es_facturable' => (bool) $request->validated('es_facturable', true),
            'archivado' => false,
            'sucursal_id' => $this->sucursalIdDe((string) $request->validated('sucursal_id', '')),
        ]);

        return response()->json(['data' => $this->presentar($persona->load('sucursal'))], 201);
    }

    /**
     * Resuelve el ULID de una sucursal a su id interno tenant-local (o null).
     */
    private function sucursalIdDe(string $ulid): ?int
    {
        if ($ulid === '') {
            return null;
        }

        $id = SucursalTenant::query()->where('ulid', $ulid)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(PersonaTenant $persona): array
    {
        return [
            'id' => $persona->ulid,
            'nombre' => $persona->nombre,
            'segundo_nombre' => $persona->segundo_nombre,
            'primer_apellido' => $persona->primer_apellido,
            'segundo_apellido' => $persona->segundo_apellido,
            'nombre_completo' => $persona->nombreCompleto(),
            'email' => $persona->email,
            'tipo' => $persona->tipo->value,
            'activo' => $persona->activo,
            'es_facturable' => $persona->es_facturable,
            'sucursal' => $persona->sucursal !== null
                ? ['id' => $persona->sucursal->ulid, 'nombre' => $persona->sucursal->nombre]
                : null,
        ];
    }
}
