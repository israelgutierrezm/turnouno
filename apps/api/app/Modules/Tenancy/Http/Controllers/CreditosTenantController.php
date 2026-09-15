<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\CreditosTenant;
use App\Modules\Tenancy\Application\LibroMayorTenant;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\RetencionCreditoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ledger de creditos del estudio (data plane del tenant): consumo directo y
 * retenciones (holds) con confirmar/liberar/perder. Concurrencia protegida en el
 * servicio (lockForUpdate en la conexion del tenant). Opera SIEMPRE sobre la BD del
 * estudio resuelto.
 */
class CreditosTenantController
{
    public function __construct(
        private readonly CreditosTenant $creditos,
        private readonly LibroMayorTenant $libro,
    ) {}

    public function consumir(Request $request): JsonResponse
    {
        $derecho = DerechoTenant::query()->where('ulid', (string) $request->route('derecho'))->firstOrFail();
        $validado = $request->validate([
            'unidades' => ['required', 'integer', 'min:1'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $movimiento = $this->creditos->consumir($derecho, (int) $validado['unidades'], $validado['descripcion'] ?? null);

        return response()->json([
            'data' => [
                'movimiento' => $movimiento->ulid,
                'saldo' => $this->libro->saldo($derecho),
                'disponible' => $this->libro->disponible($derecho),
            ],
        ], 201);
    }

    public function retener(Request $request): JsonResponse
    {
        $derecho = DerechoTenant::query()->where('ulid', (string) $request->route('derecho'))->firstOrFail();
        $validado = $request->validate([
            'unidades' => ['required', 'integer', 'min:1'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $retencion = $this->creditos->retener($derecho, (int) $validado['unidades'], $validado['descripcion'] ?? null);

        return response()->json([
            'data' => [
                'retencion' => $retencion->ulid,
                'saldo' => $this->libro->saldo($derecho),
                'disponible' => $this->libro->disponible($derecho),
            ],
        ], 201);
    }

    public function confirmar(Request $request): JsonResponse
    {
        $retencion = RetencionCreditoTenant::query()->where('ulid', (string) $request->route('retencion'))->firstOrFail();
        $this->creditos->confirmar($retencion);

        return response()->json(['data' => $this->presentarRetencion($retencion->refresh())]);
    }

    public function liberar(Request $request): JsonResponse
    {
        $retencion = RetencionCreditoTenant::query()->where('ulid', (string) $request->route('retencion'))->firstOrFail();
        $this->creditos->liberar($retencion);

        return response()->json(['data' => $this->presentarRetencion($retencion->refresh())]);
    }

    public function perder(Request $request): JsonResponse
    {
        $retencion = RetencionCreditoTenant::query()->where('ulid', (string) $request->route('retencion'))->firstOrFail();
        $this->creditos->perder($retencion);

        return response()->json(['data' => $this->presentarRetencion($retencion->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarRetencion(RetencionCreditoTenant $retencion): array
    {
        $derecho = $retencion->derecho;

        return [
            'id' => $retencion->ulid,
            'unidades' => $retencion->unidades,
            'estado' => $retencion->estado->value,
            'saldo' => $this->libro->saldo($derecho),
            'disponible' => $this->libro->disponible($derecho),
        ];
    }
}
