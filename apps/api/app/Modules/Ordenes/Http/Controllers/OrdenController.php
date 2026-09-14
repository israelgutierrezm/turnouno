<?php

declare(strict_types=1);

namespace App\Modules\Ordenes\Http\Controllers;

use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Ordenes\Application\CrearOrden;
use App\Modules\Ordenes\Http\Requests\CrearOrdenRequest;
use App\Modules\Ordenes\Models\LineaOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Personas\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Órdenes de compra (carrito → orden pendiente). El cobro va en PagoController.
 */
class OrdenController
{
    public function store(CrearOrdenRequest $request, CrearOrden $crearOrden): JsonResponse
    {
        Gate::authorize('membresias.gestionar');

        $comprador = Persona::query()
            ->where('ulid', (string) $request->validated('persona_id'))
            ->firstOrFail();

        /** @var list<array{producto_id: string, cantidad?: int|string|null, beneficiario_id?: string|null}> $itemsInput */
        $itemsInput = $request->validated('items');
        $items = [];

        foreach ($itemsInput as $item) {
            $producto = ProductoComercial::query()->where('ulid', $item['producto_id'])->firstOrFail();

            $beneficiario = null;
            $beneficiarioId = $item['beneficiario_id'] ?? null;
            if (is_string($beneficiarioId) && $beneficiarioId !== '') {
                $beneficiario = Persona::query()->where('ulid', $beneficiarioId)->firstOrFail();
            }

            $cantidad = $item['cantidad'] ?? 1;

            $items[] = [
                'producto' => $producto,
                'cantidad' => is_numeric($cantidad) ? (int) $cantidad : 1,
                'beneficiario' => $beneficiario,
            ];
        }

        $orden = $crearOrden->ejecutar($comprador, $items);

        return response()->json(['data' => $this->datos($orden)], 201);
    }

    public function show(Orden $orden): JsonResponse
    {
        Gate::authorize('membresias.ver');

        return response()->json(['data' => $this->datos($orden)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Orden $orden): array
    {
        $orden->loadMissing('lineas.producto');

        return [
            'id' => $orden->ulid,
            'estado' => $orden->estado->value,
            'total_minor' => $orden->total_minor,
            'moneda' => $orden->moneda,
            'lineas' => $orden->lineas->map(static fn (LineaOrden $linea): array => [
                'producto' => $linea->producto->nombre,
                'cantidad' => $linea->cantidad,
                'subtotal_minor' => $linea->subtotal_minor,
            ])->all(),
        ];
    }
}
