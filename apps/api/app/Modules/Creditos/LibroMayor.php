<?php

declare(strict_types=1);

namespace App\Modules\Creditos;

use App\Modules\Creditos\Models\MovimientoCredito;
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
}
