<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Controllers;

use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\Http\Requests\CrearProductoRequest;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Organizaciones\Models\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductoComercialController
{
    public function index(): JsonResponse
    {
        Gate::authorize('productos.ver');

        $productos = ProductoComercial::query()->with(['actividad', 'sucursal'])->orderBy('id')->get();

        return response()->json([
            'data' => $productos->map(fn (ProductoComercial $producto): array => $this->presentar($producto))->all(),
        ]);
    }

    public function store(CrearProductoRequest $request, CrearProducto $crearProducto): JsonResponse
    {
        Gate::authorize('productos.gestionar');

        $creditos = $request->validated('creditos_incluidos');
        $unidadesCiclo = $request->validated('unidades_por_ciclo');
        $rolloverMax = $request->validated('rollover_max');

        $producto = $crearProducto->ejecutar(
            (string) $request->validated('nombre'),
            TipoProducto::from((string) $request->validated('tipo')),
            (int) $request->validated('precio_minor'),
            (string) $request->validated('moneda'),
            (bool) $request->validated('ilimitado', false),
            is_numeric($creditos) ? (int) $creditos : null,
            PoliticaReset::from((string) $request->validated('politica_reset', PoliticaReset::Ninguno->value)),
            is_numeric($unidadesCiclo) ? (int) $unidadesCiclo : null,
            PoliticaRollover::from((string) $request->validated('politica_rollover', PoliticaRollover::Ninguno->value)),
            is_numeric($rolloverMax) ? (int) $rolloverMax : null,
            $this->resolverId(Actividad::class, $request->validated('actividad_id')),
            $this->resolverId(Sucursal::class, $request->validated('sucursal_id')),
        );

        return response()->json(['data' => $this->presentar($producto->load(['actividad', 'sucursal']))], 201);
    }

    /**
     * Traduce el ULID público de un modelo del tenant a su id interno.
     *
     * @param  class-string<Model>  $modelo
     */
    private function resolverId(string $modelo, mixed $ulid): ?int
    {
        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        $registro = $modelo::query()->where('ulid', $ulid)->first();

        return $registro?->getKey();
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
            'politica_reset' => $producto->politica_reset->value,
            'unidades_por_ciclo' => $producto->unidades_por_ciclo,
            'politica_rollover' => $producto->politica_rollover->value,
            'rollover_max' => $producto->rollover_max,
            'actividad_id' => $producto->actividad?->ulid,
            'actividad' => $producto->actividad?->nombre,
            'sucursal_id' => $producto->sucursal?->ulid,
            'sucursal' => $producto->sucursal?->nombre,
        ];
    }
}
