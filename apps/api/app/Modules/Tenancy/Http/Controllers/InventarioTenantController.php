<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Inventario\Exceptions\StockInsuficiente;
use App\Modules\Inventario\TipoMovimientoInventario;
use App\Modules\Tenancy\Application\InventarioTenant;
use App\Modules\Tenancy\Models\ArticuloTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inventario minorista del estudio, tenant-local (R21): catálogo de artículos y su stock
 * por sucursal (derivado del ledger), con entradas y ajustes. Opera SIEMPRE sobre la BD
 * del estudio resuelto.
 */
class InventarioTenantController
{
    public function __construct(private readonly InventarioTenant $inventario) {}

    public function index(): JsonResponse
    {
        $articulos = ArticuloTenant::query()->orderByDesc('id')->get();
        $sucursales = $this->mapaSucursales();

        return response()->json([
            'data' => $articulos->map(fn (ArticuloTenant $a): array => $this->presentar($a, $sucursales))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $articulo = ArticuloTenant::query()->create($this->validar($request));

        return response()->json(['data' => $this->presentar($articulo, $this->mapaSucursales())], 201);
    }

    public function actualizar(Request $request): JsonResponse
    {
        $articulo = $this->resolver($request);
        $articulo->update($this->validar($request));

        return response()->json(['data' => $this->presentar($articulo->refresh(), $this->mapaSucursales())]);
    }

    /**
     * Registra una entrada (reabastecimiento) o un ajuste de inventario del artículo.
     */
    public function movimiento(Request $request): JsonResponse
    {
        $articulo = $this->resolver($request);
        $validado = $request->validate([
            'sucursal_id' => ['required', 'string'],
            'tipo' => ['required', 'in:entrada,ajuste'],
            'cantidad' => ['required', 'integer', 'not_in:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $sucursalId = SucursalTenant::query()->where('ulid', $validado['sucursal_id'])->value('id');
        abort_if($sucursalId === null, 404);
        $sucursalId = (int) $sucursalId;

        $tipo = TipoMovimientoInventario::from($validado['tipo']);
        $cantidad = (int) $validado['cantidad'];
        // Entrada siempre suma; ajuste usa el signo tal cual.
        $delta = $tipo === TipoMovimientoInventario::Entrada ? abs($cantidad) : $cantidad;

        if ($this->inventario->stock($articulo->getKey(), $sucursalId) + $delta < 0) {
            throw new StockInsuficiente('El ajuste dejaría el stock en negativo.');
        }

        $this->inventario->registrar(
            $articulo->getKey(),
            $sucursalId,
            $delta,
            $tipo,
            $validado['motivo'] ?? null,
            $this->actor($request),
        );

        return response()->json(['data' => $this->presentar($articulo, $this->mapaSucursales())], 201);
    }

    /**
     * Mapa id interno => {ulid, nombre} de las sucursales, para presentar existencias.
     *
     * @return array<int, array{ulid: string, nombre: string}>
     */
    private function mapaSucursales(): array
    {
        return SucursalTenant::query()
            ->get(['id', 'ulid', 'nombre'])
            ->keyBy('id')
            ->map(fn (SucursalTenant $s): array => ['ulid' => (string) $s->ulid, 'nombre' => (string) $s->nombre])
            ->all();
    }

    private function resolver(Request $request): ArticuloTenant
    {
        return ArticuloTenant::query()->where('ulid', (string) $request->route('articulo'))->firstOrFail();
    }

    private function actor(Request $request): ?Usuario
    {
        $usuario = $request->attributes->get('usuario_tenant');

        return $usuario instanceof Usuario ? $usuario : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64'],
            'precio_minor' => ['required', 'integer', 'min:0'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'activo' => ['boolean'],
        ]);

        return [
            'nombre' => $validado['nombre'],
            'sku' => $validado['sku'] ?? null,
            'precio_minor' => (int) $validado['precio_minor'],
            'moneda' => strtoupper($validado['moneda'] ?? 'MXN'),
            'activo' => (bool) ($validado['activo'] ?? true),
        ];
    }

    /**
     * @param  array<int, array{ulid: string, nombre: string}>  $sucursales
     * @return array<string, mixed>
     */
    private function presentar(ArticuloTenant $articulo, array $sucursales): array
    {
        $porSucursal = $this->inventario->stockPorSucursal($articulo->getKey());
        $existencias = [];
        foreach ($porSucursal as $sucursalId => $stock) {
            $existencias[] = [
                'sucursal_id' => $sucursales[$sucursalId]['ulid'] ?? null,
                'sucursal' => $sucursales[$sucursalId]['nombre'] ?? '—',
                'stock' => $stock,
            ];
        }

        return [
            'id' => $articulo->ulid,
            'nombre' => $articulo->nombre,
            'sku' => $articulo->sku,
            'precio_minor' => $articulo->precio_minor,
            'moneda' => $articulo->moneda,
            'activo' => $articulo->activo,
            'stock_total' => array_sum($porSucursal),
            'existencias' => $existencias,
        ];
    }
}
