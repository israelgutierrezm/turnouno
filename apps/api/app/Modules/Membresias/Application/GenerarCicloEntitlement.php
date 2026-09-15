<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Application;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\EstadoAcuerdo;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avanza los ciclos vencidos de un derecho recurrente: al cerrar cada ciclo aplica
 * el rollover (lo no acarreado se **expira** en el ledger), abre el ciclo
 * siguiente y concede su cupo. Idempotente: solo avanza si el ciclo actual ya
 * venció; reejecutar cuando está al día no hace nada. Devuelve cuántos ciclos
 * avanzó. Se corre a diario con `entitlements:generar-ciclos`.
 */
class GenerarCicloEntitlement
{
    // Tope defensivo de ciclos a avanzar en una corrida (evita bucles patológicos).
    private const MAX_CICLOS = 120;

    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Derecho $derecho): int
    {
        if ($derecho->politica_reset === PoliticaReset::Ninguno || $derecho->ciclo_fin === null) {
            return 0;
        }

        // No renovar ciclos de acuerdos que ya no están activos (F-13): un acuerdo
        // cancelado o pausado no debe seguir recibiendo créditos.
        if ($derecho->acuerdo->estado !== EstadoAcuerdo::Activo) {
            return 0;
        }

        $hoy = Carbon::now()->startOfDay();

        return DB::transaction(function () use ($derecho, $hoy): int {
            $bloqueado = Derecho::query()->whereKey($derecho->getKey())->lockForUpdate()->firstOrFail();
            $ciclos = 0;

            while ($ciclos < self::MAX_CICLOS
                && $bloqueado->ciclo_fin !== null
                && $bloqueado->ciclo_fin->lt($hoy)) {
                $saldo = $this->libro->saldo($bloqueado);
                $acarreo = $this->rollover($bloqueado, $saldo);
                $expira = $saldo - $acarreo;

                if ($expira > 0) {
                    $this->libro->registrar($bloqueado, TipoMovimiento::Expiracion, -$expira, 'Expiración de ciclo');
                }

                [$inicio, $fin] = $this->siguienteVentana($bloqueado);
                $bloqueado->update(['ciclo_inicio' => $inicio, 'ciclo_fin' => $fin]);

                $cupo = $bloqueado->unidades_por_ciclo ?? 0;
                if ($cupo > 0) {
                    $this->libro->registrar($bloqueado, TipoMovimiento::Concesion, $cupo, 'Concesión de ciclo');
                }

                $bloqueado->refresh();
                $ciclos++;
            }

            return $ciclos;
        });
    }

    private function rollover(Derecho $derecho, int $saldo): int
    {
        return match ($derecho->politica_rollover) {
            PoliticaRollover::Completo => $saldo,
            PoliticaRollover::Limitado => min($saldo, $derecho->rollover_max ?? 0),
            PoliticaRollover::Ninguno => 0,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function siguienteVentana(Derecho $derecho): array
    {
        $fin = $derecho->ciclo_fin;
        $siguiente = $fin instanceof Carbon ? $fin->copy()->addDay() : Carbon::now();

        if ($derecho->politica_reset === PoliticaReset::Calendario) {
            $inicio = $siguiente->copy()->startOfMonth();

            return [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
        }

        return [$siguiente->toDateString(), $siguiente->copy()->addMonth()->subDay()->toDateString()];
    }
}
