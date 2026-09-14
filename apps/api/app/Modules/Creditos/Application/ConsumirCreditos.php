<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Application;

use App\Modules\Creditos\Exceptions\SaldoInsuficiente;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\Models\MovimientoCredito;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\Models\Derecho;
use Illuminate\Support\Facades\DB;

/**
 * Consume créditos de un derecho asentando un movimiento negativo en el ledger.
 * Bloquea el derecho (lockForUpdate) dentro de la transacción para evitar
 * sobre-consumo concurrente (ver docs/BOOKING_ENGINE.md).
 */
class ConsumirCreditos
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Derecho $derecho, int $unidades, ?string $descripcion = null): MovimientoCredito
    {
        return DB::transaction(function () use ($derecho, $unidades, $descripcion): MovimientoCredito {
            $bloqueado = Derecho::query()->whereKey($derecho->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueado->ilimitado && $this->libro->disponible($bloqueado) < $unidades) {
                throw new SaldoInsuficiente('Saldo insuficiente para el consumo.');
            }

            return $this->libro->registrar($bloqueado, TipoMovimiento::Consumo, -$unidades, $descripcion);
        });
    }
}
