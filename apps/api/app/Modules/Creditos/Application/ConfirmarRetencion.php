<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Application;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Creditos\TipoMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * Confirma una retención: asienta el consumo en el ledger y la marca consumida.
 */
class ConfirmarRetencion
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(RetencionCredito $retencion): void
    {
        DB::transaction(function () use ($retencion): void {
            $bloqueada = RetencionCredito::query()->whereKey($retencion->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado !== EstadoRetencion::Activa) {
                return;
            }

            $this->libro->registrar($bloqueada->derecho, TipoMovimiento::Consumo, -$bloqueada->unidades, 'Confirmación de retención');
            $bloqueada->update(['estado' => EstadoRetencion::Consumida]);
        });
    }
}
