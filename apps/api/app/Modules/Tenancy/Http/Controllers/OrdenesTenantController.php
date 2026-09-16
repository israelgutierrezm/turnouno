<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Pagos\MetodoPago;
use App\Modules\Tenancy\Application\CobrarOrdenTenant;
use App\Modules\Tenancy\Application\OrdenesTenant;
use App\Modules\Tenancy\Models\LineaOrdenTenant;
use App\Modules\Tenancy\Models\OrdenTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProductoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ordenes del estudio (data plane del tenant): crea ordenes pendientes con precio
 * congelado y las liquida manualmente (ventanilla), haciendo el fulfillment
 * (concesion de derechos). El cobro con pasarela real es un modulo posterior.
 * Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class OrdenesTenantController
{
    private const LIMITE = 200;

    private const METODOS = ['efectivo', 'transferencia', 'ventanilla', 'manual'];

    public function __construct(
        private readonly OrdenesTenant $ordenes,
        private readonly CobrarOrdenTenant $cobrar,
    ) {}

    public function index(): JsonResponse
    {
        $ordenes = OrdenTenant::query()->with(['persona', 'lineas.producto', 'lineas.beneficiario'])
            ->orderByDesc('id')->limit(self::LIMITE)->get();

        return response()->json([
            'data' => $ordenes->map(fn (OrdenTenant $orden): array => $this->presentar($orden))->all(),
        ]);
    }

    public function crear(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'comprador_id' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'string'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.beneficiario_id' => ['nullable', 'string'],
        ]);

        $comprador = PersonaTenant::query()->where('ulid', $validado['comprador_id'])->firstOrFail();

        $items = [];
        foreach ($validado['items'] as $item) {
            $producto = ProductoTenant::query()->where('ulid', $item['producto_id'])->firstOrFail();
            $beneficiario = isset($item['beneficiario_id']) && $item['beneficiario_id'] !== ''
                ? PersonaTenant::query()->where('ulid', $item['beneficiario_id'])->firstOrFail()
                : null;

            $items[] = [
                'producto' => $producto,
                'cantidad' => (int) $item['cantidad'],
                'beneficiario' => $beneficiario,
            ];
        }

        $orden = $this->ordenes->crear($comprador, $items);

        return response()->json(['data' => $this->presentar($orden->refresh())], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $orden = OrdenTenant::query()->where('ulid', (string) $request->route('orden'))->firstOrFail();

        return response()->json(['data' => $this->presentar($orden)]);
    }

    public function cobrar(Request $request): JsonResponse
    {
        $orden = OrdenTenant::query()->where('ulid', (string) $request->route('orden'))->firstOrFail();

        $validado = $request->validate([
            'proveedor' => ['required', 'string'],
            'metodo' => ['nullable', Rule::enum(MetodoPago::class)],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $metodo = isset($validado['metodo']) ? MetodoPago::from($validado['metodo']) : null;
        $key = ($validado['idempotency_key'] ?? '') !== '' ? $validado['idempotency_key'] : null;

        $pago = $this->cobrar->ejecutar($orden, $validado['proveedor'], $metodo, $key);

        return response()->json(['data' => [
            'pago' => $pago->ulid,
            'proveedor' => $pago->proveedor,
            'estado' => $pago->estado->value,
            'checkout' => $pago->checkout,
            'orden' => $this->presentar($orden->refresh()),
        ]], 201);
    }

    public function liquidar(Request $request): JsonResponse
    {
        $orden = OrdenTenant::query()->where('ulid', (string) $request->route('orden'))->firstOrFail();

        $validado = $request->validate([
            'metodo' => ['required', Rule::in(self::METODOS)],
            'referencia' => ['nullable', 'string', 'max:255'],
        ]);

        $liquidada = $this->ordenes->liquidar($orden, $validado['metodo'], $validado['referencia'] ?? null);

        return response()->json(['data' => $this->presentar($liquidada->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(OrdenTenant $orden): array
    {
        $orden->loadMissing(['persona', 'lineas.producto', 'lineas.beneficiario']);

        return [
            'id' => $orden->ulid,
            'comprador' => $orden->persona?->nombre,
            'estado' => $orden->estado->value,
            'total_minor' => $orden->total_minor,
            'moneda' => $orden->moneda,
            'metodo_pago' => $orden->metodo_pago,
            'referencia_pago' => $orden->referencia_pago,
            'pagada_en' => $orden->pagada_en?->toIso8601String(),
            'lineas' => $orden->lineas->map(fn (LineaOrdenTenant $linea): array => [
                'id' => $linea->ulid,
                'producto' => $linea->producto?->nombre,
                'beneficiario' => $linea->beneficiario?->nombre,
                'cantidad' => $linea->cantidad,
                'precio_unitario_minor' => $linea->precio_unitario_minor,
                'subtotal_minor' => $linea->subtotal_minor,
            ])->all(),
        ];
    }
}
