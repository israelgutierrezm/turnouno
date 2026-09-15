<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Ordenes\Application\CrearOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\Application\CobrarOrden;
use App\Modules\Pagos\MetodoPago;
use App\Modules\Pagos\Pasarelas\RegistroDePasarelas;
use App\Modules\Portal\Http\Requests\CrearOrdenPortalRequest;
use App\Modules\Portal\Http\Requests\PagarRequest;
use App\Modules\Portal\Support\MiembroActual;
use Illuminate\Http\JsonResponse;

/**
 * Compra desde el portal: catálogo, crear una orden para el propio miembro y
 * cobrarla (devuelve los datos de checkout para el cliente).
 */
class CompraController
{
    public function __construct(private readonly MiembroActual $miembro) {}

    public function productos(): JsonResponse
    {
        $productos = ProductoComercial::query()->orderBy('nombre')->get();

        return response()->json([
            'data' => $productos->map(static fn (ProductoComercial $producto): array => [
                'id' => $producto->ulid,
                'nombre' => $producto->nombre,
                'tipo' => $producto->tipo->value,
                'precio_minor' => $producto->precio_minor,
                'moneda' => $producto->moneda,
                'ilimitado' => $producto->ilimitado,
            ])->all(),
        ]);
    }

    public function crearOrden(CrearOrdenPortalRequest $request, CrearOrden $crear): JsonResponse
    {
        $persona = $this->miembro->persona();

        $producto = ProductoComercial::query()
            ->where('ulid', (string) $request->validated('producto_id'))
            ->firstOrFail();

        $orden = $crear->ejecutar($persona, [['producto' => $producto, 'cantidad' => 1]]);

        return response()->json([
            'data' => [
                'id' => $orden->ulid,
                'total_minor' => $orden->total_minor,
                'moneda' => $orden->moneda,
                'estado' => $orden->estado->value,
            ],
        ], 201);
    }

    public function pagar(PagarRequest $request, Orden $orden, CobrarOrden $cobrar, RegistroDePasarelas $registro): JsonResponse
    {
        $persona = $this->miembro->persona();
        abort_unless($orden->persona_id === $persona->id, 403);

        $pasarela = $registro->para((string) $request->validated('proveedor'));
        $metodoValor = $request->validated('metodo');
        $metodo = is_string($metodoValor) && $metodoValor !== '' ? MetodoPago::from($metodoValor) : null;

        $datosCliente = array_filter([
            'card_token' => $request->validated('card_token'),
            'device_session_id' => $request->validated('device_session_id'),
        ], static fn ($valor): bool => is_string($valor) && $valor !== '');

        $pago = $cobrar->ejecutar($orden, $pasarela, null, $metodo, $datosCliente);

        return response()->json([
            'data' => [
                'id' => $pago->ulid,
                'estado' => $pago->estado->value,
                'proveedor' => $pago->proveedor,
                'orden_estado' => $orden->refresh()->estado->value,
                'checkout' => $pago->checkout,
            ],
        ], 201);
    }
}
