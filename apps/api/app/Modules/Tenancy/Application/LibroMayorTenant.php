<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\MovimientoCreditoTenant;

/**
 * Ledger de creditos tenant-local. Asienta movimientos y deriva el saldo de un
 * derecho como la suma de sus movimientos (fuente de verdad auditable, nunca un
 * saldo guardado). Opera sobre la BD del tenant resuelto.
 */
class LibroMayorTenant
{
    public function registrar(DerechoTenant $derecho, TipoMovimiento $tipo, int $unidades, ?string $descripcion = null): MovimientoCreditoTenant
    {
        return $derecho->movimientos()->create([
            'tipo' => $tipo,
            'unidades' => $unidades,
            'descripcion' => $descripcion,
        ]);
    }

    public function saldo(DerechoTenant $derecho): int
    {
        return (int) $derecho->movimientos()->sum('unidades');
    }

    /**
     * Disponible = saldo del ledger − retenciones (holds) activas.
     */
    public function disponible(DerechoTenant $derecho): int
    {
        $retenido = (int) $derecho->retenciones()
            ->where('estado', EstadoRetencion::Activa)
            ->sum('unidades');

        return $this->saldo($derecho) - $retenido;
    }
}
