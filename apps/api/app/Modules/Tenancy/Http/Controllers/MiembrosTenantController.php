<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Tenancy\Http\Requests\CrearMiembroRequest;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
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
        $busqueda = trim((string) $request->query('q', ''));

        $personas = PersonaTenant::query()
            ->with('sucursal')
            ->where('tipo', $tipo)
            ->where('archivado', false)
            // Filtro opcional por sucursal de casa (R18).
            ->when($this->sucursalIdDe((string) $request->query('sucursal_id', '')), fn ($q, int $id) => $q->where('sucursal_id', $id))
            // Búsqueda server-side (nombre/apellidos/email): el buscador global de
            // Recepción no debe perder al alumno 101 (antes topaba en LIMITE sin filtro).
            ->when($busqueda !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('nombre', 'like', "%{$busqueda}%")
                ->orWhere('segundo_nombre', 'like', "%{$busqueda}%")
                ->orWhere('primer_apellido', 'like', "%{$busqueda}%")
                ->orWhere('segundo_apellido', 'like', "%{$busqueda}%")
                ->orWhere('email', 'like', "%{$busqueda}%")))
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get();

        // First-timer (R14): asistencias `presente` por persona en un solo query (sin N+1);
        // 0 asistencias = primerizo (nunca ha asistido).
        $asistencias = $this->conteoAsistencias($personas->pluck('id')->all());

        return response()->json([
            'data' => $personas->map(fn (PersonaTenant $persona): array => $this->presentar(
                $persona,
                (int) ($asistencias[$persona->getKey()] ?? 0),
            ))->all(),
        ]);
    }

    /**
     * Cuenta las asistencias `presente` por persona (R14) en un solo query.
     *
     * @param  list<int>  $personaIds
     * @return array<int, int>
     */
    private function conteoAsistencias(array $personaIds): array
    {
        if ($personaIds === []) {
            return [];
        }

        return ReservaTenant::query()
            ->join('asistencias', 'asistencias.reserva_id', '=', 'reservas.id')
            ->where('asistencias.estado', EstadoAsistencia::Presente->value)
            ->whereIn('reservas.persona_id', $personaIds)
            ->groupBy('reservas.persona_id')
            ->selectRaw('reservas.persona_id as pid, count(*) as total')
            ->pluck('total', 'pid')
            ->map(fn ($v): int => (int) $v)
            ->all();
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
    private function presentar(PersonaTenant $persona, ?int $asistencias = null): array
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
            'asistencias' => $asistencias,
            'primera_vez' => $asistencias !== null ? $asistencias === 0 : null,
            'sucursal' => $persona->sucursal !== null
                ? ['id' => $persona->sucursal->ulid, 'nombre' => $persona->sucursal->nombre]
                : null,
        ];
    }
}
