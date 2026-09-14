<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Controllers;

use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\Http\Requests\CrearProductoRequest;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Membresias\TipoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductoComercialController
{
    public function index(): JsonResponse
    {
        Gate::authorize('productos.ver');

        $productos = ProductoComercial::query()->orderBy('id')->get();

        return response()->json([
            'data' => $productos->map(fn (ProductoComercial $producto): array => $this->presentar($producto))->all(),
        ]);
    }

    public function store(CrearProductoRequest $request, CrearProducto $crearProducto): JsonResponse
    {
        Gate::authorize('productos.gestionar');

        $creditos = $request->validated('creditos_incluidos');

        $producto = $crearProducto->ejecutar(
            (string) $request->validated('nombre'),
            TipoProducto::from((string) $request->validated('tipo')),
            (int) $request->validated('precio_minor'),
            (string) $request->validated('moneda'),
            (bool) $request->validated('ilimitado', false),
            is_numeric($creditos) ? (int) $creditos : null,
        );

        return response()->json(['data' => $this->presentar($producto)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(ProductoComercial $producto): array
    {
        return [
            'id' => $producto->ulid,
            'nombre' => $producto->nombre,
            'tipo' => $producto->tipo->value,
            'precio_minor' => $producto->precio_minor,
            'moneda' => $producto->moneda,
            'ilimitado' => $producto->ilimitado,
            'creditos_incluidos' => $producto->creditos_incluidos,
        ];
    }
}
