<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\AuditoriaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bitacora de auditoria del estudio (append-only): quien hizo que sobre que entidad.
 * Solo lectura y gateada por permiso; permite investigar operaciones sensibles
 * (creditos, pagos, overrides) sin pedir logs al equipo tecnico. Opera sobre la BD
 * del estudio resuelto.
 */
class AuditoriaController
{
    private const LIMITE = 100;

    public function index(Request $request): JsonResponse
    {
        $consulta = AuditoriaTenant::query()->orderByDesc('id');

        $entidadTipo = trim((string) $request->query('entidad_tipo', ''));
        if ($entidadTipo !== '') {
            $consulta->where('entidad_tipo', $entidadTipo);
        }

        $accion = trim((string) $request->query('accion', ''));
        if ($accion !== '') {
            $consulta->where('accion', $accion);
        }

        $asientos = $consulta->limit(self::LIMITE)->get();

        return response()->json([
            'data' => $asientos->map(fn (AuditoriaTenant $a): array => [
                'id' => $a->ulid,
                'actor' => $a->actor_nombre,
                'accion' => $a->accion,
                'entidad_tipo' => $a->entidad_tipo,
                'entidad_id' => $a->entidad_id,
                'motivo' => $a->motivo,
                'antes' => $a->antes,
                'despues' => $a->despues,
                'ip' => $a->ip,
                'correlation_id' => $a->correlation_id,
                'fecha' => $a->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
