<?php

declare(strict_types=1);

namespace App\Modules\Ordenes\Application;

use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\Application\CobrarOrden;
use App\Modules\Personas\Models\Persona;
use Illuminate\Support\Facades\DB;

/**
 * Crea una orden pendiente para un comprador con sus líneas. El precio de cada
 * línea se congela desde el producto (snapshot) y el total es la suma. El pago y
 * el fulfillment (concesión de derechos) ocurren después con {@see CobrarOrden}.
 */
class CrearOrden
{
    /**
     * @param  list<array{producto: ProductoComercial, cantidad: int, beneficiario?: Persona|null}>  $items
     */
    public function ejecutar(Persona $comprador, array $items): Orden
    {
        return DB::transaction(function () use ($comprador, $items): Orden {
            $moneda = $items[0]['producto']->moneda; // MVP: una sola moneda por orden.
            $total = 0;

            $orden = Orden::create([
                'persona_id' => $comprador->id,
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
                    'producto_comercial_id' => $producto->id,
                    'beneficiario_id' => ($item['beneficiario'] ?? null)?->id,
                    'cantidad' => $cantidad,
                    'precio_unitario_minor' => $producto->precio_minor,
                    'subtotal_minor' => $subtotal,
                ]);
            }

            $orden->update(['total_minor' => $total]);

            return $orden;
        });
    }
}
