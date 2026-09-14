<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Application;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Creditos\TipoMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * Pierde (forfeit) una retención: las unidades se consumen sin servicio
 * (p. ej. cancelación tardía). Asienta el consumo y la marca perdida.
 */
class PerderRetencion
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(RetencionCredito $retencion): void
    {
        DB::transaction(function () use ($retencion): void {
            $bloqueada = RetencionCredito::query()->whereKey($retencion->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado !== EstadoRetencion::Activa) {
                return;
            }

            $this->libro->registrar($bloqueada->derecho, TipoMovimiento::Consumo, -$bloqueada->unidades, 'Retención perdida (forfeit)');
            $bloqueada->update(['estado' => EstadoRetencion::Perdida]);
        });
    }
}
