<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Exceptions\MonedaMixta;
use App\Modules\Ordenes\Exceptions\OrdenNoLiquidable;
use App\Modules\Tenancy\Models\OrdenTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProductoTenant;
use Illuminate\Support\Facades\DB;

/**
 * Ordenes tenant-local: crea una orden pendiente con sus lineas (precio congelado)
 * y la liquida manualmente (ventanilla/efectivo/transferencia), haciendo el
 * fulfillment: concede un derecho por cada unidad de cada linea al beneficiario (o
 * comprador). El cobro con pasarela real es un modulo posterior. Comprador !=
 * participante. Todo sobre la BD del tenant resuelto.
 */
class OrdenesTenant
{
    public function __construct(private readonly MembresiasTenant $membresias) {}

    /**
     * @param  list<array{producto: ProductoTenant, cantidad: int, beneficiario?: PersonaTenant|null}>  $items
     */
    public function crear(PersonaTenant $comprador, array $items): OrdenTenant
    {
        $moneda = $items[0]['producto']->moneda; // Una sola moneda por orden.

        foreach ($items as $item) {
            if ($item['producto']->moneda !== $moneda) {
                throw new MonedaMixta('Una orden no puede mezclar monedas.');
            }
        }

        return DB::connection('tenant')->transaction(function () use ($comprador, $items, $moneda): OrdenTenant {
            $total = 0;

            $orden = OrdenTenant::query()->create([
                'persona_id' => $comprador->getKey(),
                'estado' => EstadoOrden::Pendiente->value,
                'total_minor' => 0,
                'moneda' => $moneda,
            ]);

            foreach ($items as $item) {
                $producto = $item['producto'];
                $cantidad = max(1, $item['cantidad']);
                $subtotal = $producto->precio_minor * $cantidad;
                $total += $subtotal;

                $orden->lineas()->create([
                    'producto_comercial_id' => $producto->getKey(),
                    'beneficiario_id' => ($item['beneficiario'] ?? null)?->getKey(),
                    'cantidad' => $cantidad,
                    'precio_unitario_minor' => $producto->precio_minor,
                    'subtotal_minor' => $subtotal,
                ]);
            }

            $orden->update(['total_minor' => $total]);

            return $orden;
        });
    }

    /**
     * Liquida una orden pendiente (pago manual/ventanilla) y hace el fulfillment de
     * forma atomica. Idempotente: una orden ya pagada devuelve sin volver a conceder
     * derechos. La orden cancelada no admite liquidacion.
     */
    public function liquidar(OrdenTenant $orden, string $metodo, ?string $referencia = null): OrdenTenant
    {
        return DB::connection('tenant')->transaction(function () use ($orden, $metodo, $referencia): OrdenTenant {
            $bloqueada = OrdenTenant::query()->whereKey($orden->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado === EstadoOrden::Pagada) {
                return $bloqueada;
            }

            if ($bloqueada->estado === EstadoOrden::Cancelada) {
                throw new OrdenNoLiquidable('La orden no admite liquidacion.');
            }

            $bloqueada->update([
                'estado' => EstadoOrden::Pagada->value,
                'metodo_pago' => $metodo,
                'referencia_pago' => $referencia,
                'pagada_en' => now(),
            ]);

            $bloqueada->loadMissing(['lineas.producto', 'lineas.beneficiario', 'persona']);

            foreach ($bloqueada->lineas as $linea) {
                $beneficiario = $linea->beneficiario ?? $bloqueada->persona;
                $producto = $linea->producto;

                if (! $beneficiario instanceof PersonaTenant || ! $producto instanceof ProductoTenant) {
                    continue;
                }

                for ($i = 0; $i < $linea->cantidad; $i++) {
                    $acuerdo = $this->membresias->venderProducto($beneficiario, $producto);
                    $acuerdo->update(['linea_orden_id' => $linea->getKey()]);
                }
            }

            return $bloqueada;
        });
    }
}
