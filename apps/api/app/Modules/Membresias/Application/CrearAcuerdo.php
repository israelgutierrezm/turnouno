<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Application;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\Models\Acuerdo;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Personas\Models\Persona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra la compra de un producto: crea el acuerdo, otorga el derecho (copiando
 * la plantilla de ciclo/rollover/restricciones del producto) y concede sus
 * créditos en el ledger. Para un producto recurrente inicializa el primer ciclo y
 * concede su cupo; para un pack, concede sus créditos incluidos. Todo en una sola
 * transacción (ver ADR-0004, ADR-0009 y MEMBERSHIP_ENGINE).
 */
class CrearAcuerdo
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Persona $persona, ProductoComercial $producto, ?string $fechaInicio = null): Acuerdo
    {
        return DB::transaction(function () use ($persona, $producto, $fechaInicio): Acuerdo {
            $inicio = $fechaInicio ?? Carbon::now()->toDateString();

            $acuerdo = Acuerdo::create([
                'persona_id' => $persona->id,
                'producto_comercial_id' => $producto->id,
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
                        $recurrente ? 'Concesión de ciclo' : 'Concesión inicial',
                    );
                }
            }

            return $acuerdo;
        });
    }

    /**
     * Ventana del primer ciclo según la política de reset.
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

        // Aniversario: desde la fecha, un mes menos un día.
        return [
            $dia->toDateString(),
            $dia->copy()->addMonth()->subDay()->toDateString(),
        ];
    }
}
