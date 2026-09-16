<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Tenancy\Application\LibroMayorTenant;
use App\Modules\Tenancy\Application\MembresiasTenant;
use App\Modules\Tenancy\Application\RegistrarAuditoria;
use App\Modules\Tenancy\Models\ActividadTenant;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProductoTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Membresias del estudio (data plane del tenant): productos vendibles, venta
 * (acuerdo → derecho + concesion de creditos) y consulta de derechos con su saldo
 * derivado del ledger. Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class MembresiasTenantController
{
    private const LIMITE = 200;

    public function __construct(
        private readonly MembresiasTenant $membresias,
        private readonly LibroMayorTenant $libro,
        private readonly RegistrarAuditoria $auditoria,
    ) {}

    public function productos(): JsonResponse
    {
        $productos = ProductoTenant::query()->orderByDesc('id')->limit(self::LIMITE)->get();

        return response()->json([
            'data' => $productos->map(fn (ProductoTenant $producto): array => $this->presentarProducto($producto))->all(),
        ]);
    }

    public function crearProducto(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoProducto::class)],
            'precio_minor' => ['required', 'integer', 'min:0'],
            'moneda' => ['required', 'string', 'size:3'],
            'ilimitado' => ['boolean'],
            'creditos_incluidos' => ['nullable', 'integer', 'min:0'],
            'politica_reset' => ['nullable', Rule::enum(PoliticaReset::class)],
            'unidades_por_ciclo' => ['nullable', 'integer', 'min:0'],
            'politica_rollover' => ['nullable', Rule::enum(PoliticaRollover::class)],
            'rollover_max' => ['nullable', 'integer', 'min:0'],
            'actividad_id' => ['nullable', 'string'],
            'sucursal_id' => ['nullable', 'string'],
        ]);

        $producto = $this->membresias->crearProducto(
            $validado['nombre'],
            TipoProducto::from($validado['tipo']),
            (int) $validado['precio_minor'],
            $validado['moneda'],
            (bool) ($validado['ilimitado'] ?? false),
            isset($validado['creditos_incluidos']) ? (int) $validado['creditos_incluidos'] : null,
            isset($validado['politica_reset']) ? PoliticaReset::from($validado['politica_reset']) : PoliticaReset::Ninguno,
            isset($validado['unidades_por_ciclo']) ? (int) $validado['unidades_por_ciclo'] : null,
            isset($validado['politica_rollover']) ? PoliticaRollover::from($validado['politica_rollover']) : PoliticaRollover::Ninguno,
            isset($validado['rollover_max']) ? (int) $validado['rollover_max'] : null,
            $this->resolverId(ActividadTenant::class, $validado['actividad_id'] ?? null),
            $this->resolverId(SucursalTenant::class, $validado['sucursal_id'] ?? null),
        );

        return response()->json(['data' => $this->presentarProducto($producto)], 201);
    }

    public function vender(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'persona_id' => ['required', 'string'],
            'producto_id' => ['required', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
        ]);

        $persona = PersonaTenant::query()->where('ulid', $validado['persona_id'])->firstOrFail();
        $producto = ProductoTenant::query()->where('ulid', $validado['producto_id'])->firstOrFail();

        $acuerdo = $this->membresias->venderProducto($persona, $producto, $validado['fecha_inicio'] ?? null);
        $derecho = $acuerdo->derechos()->firstOrFail();

        return response()->json([
            'data' => [
                'acuerdo' => $acuerdo->ulid,
                'persona' => $persona->ulid,
                'producto' => $producto->nombre,
                'derecho' => $this->presentarDerecho($derecho),
            ],
        ], 201);
    }

    public function derechos(Request $request): JsonResponse
    {
        $persona = PersonaTenant::query()->where('ulid', (string) $request->route('persona'))->firstOrFail();

        $derechos = DerechoTenant::query()
            ->whereHas('acuerdo', fn ($q) => $q->where('persona_id', $persona->getKey()))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $derechos->map(fn (DerechoTenant $derecho): array => $this->presentarDerecho($derecho))->all(),
        ]);
    }

    public function topUp(Request $request): JsonResponse
    {
        $derecho = DerechoTenant::query()->where('ulid', (string) $request->route('derecho'))->firstOrFail();
        $validado = $request->validate([
            'unidades' => ['required', 'integer', 'min:1'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $unidades = (int) $validado['unidades'];
        $descripcion = is_string($validado['descripcion'] ?? null) ? $validado['descripcion'] : null;
        $this->membresias->agregarTopUp($derecho, $unidades, $descripcion);

        // Concesion manual de credito = operacion sensible: se audita (quien y cuanto).
        $actor = $request->attributes->get('usuario_tenant');
        $this->auditoria->registrar(
            $actor instanceof Usuario ? $actor : null,
            'credito.top_up',
            'derecho',
            $derecho->ulid,
            null,
            ['unidades' => $unidades, 'saldo_nuevo' => $this->libro->saldo($derecho->refresh())],
            $descripcion,
        );

        return response()->json(['data' => $this->presentarDerecho($derecho->refresh())], 201);
    }

    /**
     * Resuelve un ulid a la PK interna de un modelo tenant (o null).
     *
     * @param  class-string<ActividadTenant|SucursalTenant>  $modelo
     */
    private function resolverId(string $modelo, ?string $ulid): ?int
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }

        $encontrado = $modelo::query()->where('ulid', $ulid)->first();

        return $encontrado instanceof $modelo ? (int) $encontrado->getKey() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarProducto(ProductoTenant $producto): array
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarDerecho(DerechoTenant $derecho): array
    {
        return [
            'id' => $derecho->ulid,
            'ambito' => $derecho->ambito,
            'ilimitado' => $derecho->ilimitado,
            'saldo' => $derecho->ilimitado ? null : $this->libro->saldo($derecho),
            'disponible' => $derecho->ilimitado ? null : $this->libro->disponible($derecho),
        ];
    }
}
