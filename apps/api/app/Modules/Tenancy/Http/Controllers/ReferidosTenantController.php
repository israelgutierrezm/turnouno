<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Ordenes\TipoPromocion;
use App\Modules\Referidos\EstadoReferido;
use App\Modules\Tenancy\Application\GestionarReferidosTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProgramaReferidosTenant;
use App\Modules\Tenancy\Models\ReferidoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Programa de referidos del estudio, tenant-local (R23): configuración del premio,
 * listado de referidos (con su estado y cupón) y código de referido de cada miembro.
 * Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class ReferidosTenantController
{
    public function __construct(private readonly GestionarReferidosTenant $referidos) {}

    public function index(): JsonResponse
    {
        $referidos = ReferidoTenant::query()
            ->with(['referidor', 'prospecto', 'recompensa'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $referidos->map(fn (ReferidoTenant $r): array => $this->presentar($r))->all(),
            'programa' => $this->presentarPrograma($this->referidos->programa()),
            'resumen' => [
                'pendientes' => $referidos->where('estado', EstadoReferido::Pendiente)->count(),
                'convertidos' => $referidos->where('estado', EstadoReferido::Convertido)->count(),
            ],
        ]);
    }

    public function programa(): JsonResponse
    {
        return response()->json(['data' => $this->presentarPrograma($this->referidos->programa())]);
    }

    public function guardarPrograma(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'recompensa_tipo' => ['required', Rule::enum(TipoPromocion::class)],
            'recompensa_valor' => ['required', 'integer', 'min:1'],
            'vigencia_dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'activo' => ['boolean'],
        ]);

        $programa = $this->referidos->guardarPrograma([
            'recompensa_tipo' => $validado['recompensa_tipo'],
            'recompensa_valor' => (int) $validado['recompensa_valor'],
            'vigencia_dias' => (int) $validado['vigencia_dias'],
            'activo' => (bool) ($validado['activo'] ?? true),
        ]);

        return response()->json(['data' => $this->presentarPrograma($programa)]);
    }

    public function codigo(Request $request): JsonResponse
    {
        $persona = PersonaTenant::query()->where('ulid', (string) $request->route('persona'))->firstOrFail();
        $codigo = $this->referidos->codigoDe($persona);

        return response()->json(['data' => [
            'persona' => $persona->nombreCompleto(),
            'codigo' => $codigo->codigo,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(ReferidoTenant $referido): array
    {
        return [
            'id' => $referido->ulid,
            'referidor' => $referido->referidor?->nombreCompleto(),
            'codigo' => $referido->codigo,
            'referido' => $referido->prospecto?->nombre,
            'estado' => $referido->estado->value,
            'recompensa' => $referido->recompensa?->codigo,
            'convertido_en' => $referido->convertido_en?->toIso8601String(),
            'creado_en' => $referido->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarPrograma(ProgramaReferidosTenant $programa): array
    {
        return [
            'recompensa_tipo' => $programa->recompensa_tipo->value,
            'recompensa_valor' => $programa->recompensa_valor,
            'vigencia_dias' => $programa->vigencia_dias,
            'activo' => $programa->activo,
        ];
    }
}
