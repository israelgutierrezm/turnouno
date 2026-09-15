<?php

declare(strict_types=1);

namespace App\Modules\Creditos;

use App\Modules\Creditos\Models\MovimientoCredito;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Membresias\Models\Derecho;

/**
 * Ledger de créditos. Registra asientos y deriva el saldo de un derecho como la
 * suma de sus movimientos (fuente de verdad auditable, nunca un saldo guardado).
 */
class LibroMayor
{
    public function registrar(Derecho $derecho, TipoMovimiento $tipo, int $unidades, ?string $descripcion = null): MovimientoCredito
    {
        return $derecho->movimientos()->create([
            'tipo' => $tipo,
            'unidades' => $unidades,
            'descripcion' => $descripcion,
        ]);
    }

    public function saldo(Derecho $derecho): int
    {
        return (int) $derecho->movimientos()->sum('unidades');
    }

    /**
     * Disponible = saldo del ledger − retenciones (holds) activas.
     */
    public function disponible(Derecho $derecho): int
    {
        $retenido = (int) $derecho->retenciones()
            ->where('estado', EstadoRetencion::Activa)
            ->sum('unidades');

        return $this->saldo($derecho) - $retenido;
    }

    /**
     * Proyección de saldo y disponible para varios derechos en dos consultas
     * agregadas (evita el N+1 de llamar saldo()/disponible() por derecho, F-17).
     *
     * @param  list<int>  $derechoIds
     * @return array<int, array{saldo: int, disponible: int}>
     */
    public function proyeccion(array $derechoIds): array
    {
        if ($derechoIds === []) {
            return [];
        }

        $saldos = MovimientoCredito::query()
            ->whereIn('derecho_id', $derechoIds)
            ->groupBy('derecho_id')
            ->selectRaw('derecho_id, SUM(unidades) as total')
            ->pluck('total', 'derecho_id');

        $retenciones = RetencionCredito::query()
            ->whereIn('derecho_id', $derechoIds)
            ->where('estado', EstadoRetencion::Activa)
            ->groupBy('derecho_id')
            ->selectRaw('derecho_id, SUM(unidades) as total')
            ->pluck('total', 'derecho_id');

        $resultado = [];
        foreach ($derechoIds as $id) {
            $saldo = (int) ($saldos[$id] ?? 0);
            $retenido = (int) ($retenciones[$id] ?? 0);
            $resultado[$id] = ['saldo' => $saldo, 'disponible' => $saldo - $retenido];
        }

        return $resultado;
    }
}
