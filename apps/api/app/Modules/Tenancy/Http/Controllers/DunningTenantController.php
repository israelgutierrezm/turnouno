<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\GestionarDunningTenant;
use App\Modules\Tenancy\EstadoDunning;
use App\Modules\Tenancy\Models\AcuerdoTenant;
use App\Modules\Tenancy\Models\ProcesoDunningTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dunning tenant-local (R10): registrar fallos de cobro de membresías, regularizar
 * y consultar los procesos de morosidad abiertos (morosos). Opera sobre la BD del
 * estudio resuelto; gateado por permisos tenant-local.
 */
class DunningTenantController
{
    public function __construct(private readonly GestionarDunningTenant $dunning) {}

    /**
     * Procesos de morosidad abiertos (en mora o suspendidos).
     */
    public function index(): JsonResponse
    {
        $procesos = ProcesoDunningTenant::query()
            ->whereIn('estado', [EstadoDunning::EnMora->value, EstadoDunning::Suspendido->value])
            ->with('acuerdo.persona')
            ->orderBy('gracia_hasta')
            ->get();

        return response()->json([
            'data' => $procesos->map(fn (ProcesoDunningTenant $p): array => $this->presentar($p))->all(),
        ]);
    }

    /**
     * Registra un fallo de cobro de la membresía (abre/avanza el dunning).
     */
    public function registrarFallo(Request $request): JsonResponse
    {
        $acuerdo = $this->acuerdoDe($request);
        $validado = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);

        $proceso = $this->dunning->registrarFallo($acuerdo, (string) ($validado['motivo'] ?? 'Cobro rechazado'));

        return response()->json(['data' => $this->presentar($proceso->load('acuerdo.persona'))], 201);
    }

    /**
     * Registra que el pago se resolvió (cierra el dunning y reactiva el acuerdo).
     */
    public function regularizar(Request $request): JsonResponse
    {
        $acuerdo = $this->acuerdoDe($request);

        $proceso = $this->dunning->registrarPago($acuerdo);

        return response()->json([
            'data' => $proceso !== null ? $this->presentar($proceso->load('acuerdo.persona')) : null,
        ]);
    }

    private function acuerdoDe(Request $request): AcuerdoTenant
    {
        return AcuerdoTenant::query()->where('ulid', (string) $request->route('acuerdo'))->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(ProcesoDunningTenant $proceso): array
    {
        $acuerdo = $proceso->acuerdo;
        $persona = $acuerdo?->persona;

        return [
            'id' => $proceso->ulid,
            'acuerdo' => $acuerdo?->ulid,
            'persona' => $persona !== null ? ['id' => $persona->ulid, 'nombre' => $persona->nombreCompleto()] : null,
            'estado' => $proceso->estado->value,
            'intentos' => $proceso->intentos,
            'gracia_hasta' => $proceso->gracia_hasta->toIso8601String(),
            'proximo_intento_en' => $proceso->proximo_intento_en?->toIso8601String(),
            'suspendido_en' => $proceso->suspendido_en?->toIso8601String(),
            'ultimo_motivo' => $proceso->ultimo_motivo,
        ];
    }
}
