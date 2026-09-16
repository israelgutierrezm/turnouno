<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Creditos\OrigenMovimiento;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Tenancy\Models\AcuerdoTenant;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\MovimientoCreditoTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProductoTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membresias tenant-local: crea productos, los vende (acuerdo → derecho + concesion
 * de creditos) y agrega top-ups. El derecho copia la plantilla de
 * ciclo/rollover/restricciones del producto. Todo sobre la BD del tenant resuelto.
 */
class MembresiasTenant
{
    public function __construct(private readonly LibroMayorTenant $libro) {}

    public function crearProducto(
        string $nombre,
        TipoProducto $tipo,
        int $precioMinor,
        string $moneda,
        bool $ilimitado,
        ?int $creditosIncluidos,
        PoliticaReset $politicaReset = PoliticaReset::Ninguno,
        ?int $unidadesPorCiclo = null,
        PoliticaRollover $politicaRollover = PoliticaRollover::Ninguno,
        ?int $rolloverMax = null,
        ?int $actividadId = null,
        ?int $sucursalId = null,
    ): ProductoTenant {
        $recurrente = $politicaReset !== PoliticaReset::Ninguno;

        return ProductoTenant::query()->create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'precio_minor' => $precioMinor,
            'moneda' => $moneda,
            'ilimitado' => $ilimitado,
            'creditos_incluidos' => $ilimitado ? null : $creditosIncluidos,
            'politica_reset' => $politicaReset,
            'unidades_por_ciclo' => ($ilimitado || ! $recurrente) ? null : $unidadesPorCiclo,
            'politica_rollover' => $politicaRollover,
            'rollover_max' => $politicaRollover === PoliticaRollover::Limitado ? $rolloverMax : null,
            'actividad_id' => $actividadId,
            'sucursal_id' => $sucursalId,
        ]);
    }

    /**
     * Vende un producto a una persona: crea el acuerdo, otorga el derecho (copiando
     * la plantilla del producto) y concede sus creditos en el ledger, todo en una
     * sola transaccion. Producto recurrente: inicializa el primer ciclo y concede su
     * cupo; pack: concede sus creditos incluidos.
     */
    public function venderProducto(PersonaTenant $persona, ProductoTenant $producto, ?string $fechaInicio = null, ?Usuario $actor = null): AcuerdoTenant
    {
        return DB::connection('tenant')->transaction(function () use ($persona, $producto, $fechaInicio, $actor): AcuerdoTenant {
            $inicio = $fechaInicio ?? Carbon::now()->toDateString();

            $acuerdo = AcuerdoTenant::query()->create([
                'persona_id' => $persona->getKey(),
                'producto_comercial_id' => $producto->getKey(),
                'fecha_inicio' => $inicio,
                'estado' => 'activo',
            ]);

            $politicaReset = $producto->politica_reset ?? PoliticaReset::Ninguno;
            $politicaRollover = $producto->politica_rollover ?? PoliticaRollover::Ninguno;

            $recurrente = $politicaReset !== PoliticaReset::Ninguno;
            [$cicloInicio, $cicloFin] = $recurrente
                ? $this->ventanaCiclo($politicaReset, $inicio)
                : [null, null];

            $derecho = $acuerdo->derechos()->create([
                'ambito' => 'general',
                'actividad_id' => $producto->actividad_id,
                'sucursal_id' => $producto->sucursal_id,
                'ilimitado' => $producto->ilimitado,
                'politica_reset' => $politicaReset->value,
                'unidades_por_ciclo' => $producto->unidades_por_ciclo,
                'politica_rollover' => $politicaRollover->value,
                'rollover_max' => $producto->rollover_max,
                'ciclo_inicio' => $cicloInicio,
                'ciclo_fin' => $cicloFin,
            ]);

            if (! $producto->ilimitado) {
                $concesion = $recurrente
                    ? ($producto->unidades_por_ciclo ?? 0)
                    : ($producto->creditos_incluidos ?? 0);

                if ($concesion > 0) {
                    $this->libro->registrar(
                        $derecho,
                        TipoMovimiento::Concesion,
                        $concesion,
                        $recurrente ? 'Concesion de ciclo' : 'Concesion inicial',
                        ContextoMovimiento::para(OrigenMovimiento::Venta, 'acuerdo', $acuerdo->ulid, $actor),
                    );
                }
            }

            return $acuerdo;
        });
    }

    /**
     * Agrega un add-on / top-up a un derecho: un asiento adicional en el ledger, sin
     * editar la membresia original. Bloquea el derecho para que la instantánea de
     * `saldo_posterior` sea consistente ante top-ups concurrentes, y deja trazable
     * quién concedió el crédito.
     */
    public function agregarTopUp(DerechoTenant $derecho, int $unidades, ?string $descripcion = null, ?Usuario $actor = null): MovimientoCreditoTenant
    {
        return DB::connection('tenant')->transaction(function () use ($derecho, $unidades, $descripcion, $actor): MovimientoCreditoTenant {
            $bloqueado = DerechoTenant::query()->whereKey($derecho->getKey())->lockForUpdate()->firstOrFail();

            return $this->libro->registrar(
                $bloqueado,
                TipoMovimiento::AddOn,
                $unidades,
                $descripcion ?? 'Add-on / top-up',
                ContextoMovimiento::para(OrigenMovimiento::TopUp, null, null, $actor),
            );
        });
    }

    /**
     * Ventana del primer ciclo segun la politica de reset.
     *
     * @return array{0: string, 1: string}
     */
    private function ventanaCiclo(PoliticaReset $politica, string $fecha): array
    {
        $dia = Carbon::parse($fecha);

        if ($politica === PoliticaReset::Calendario) {
            return [
                $dia->copy()->startOfMonth()->toDateString(),
                $dia->copy()->endOfMonth()->toDateString(),
            ];
        }

        // Aniversario: desde la fecha, un mes menos un dia.
        return [
            $dia->toDateString(),
            $dia->copy()->addMonth()->subDay()->toDateString(),
        ];
    }
}
