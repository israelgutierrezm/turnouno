<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Application;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\Models\RetencionCredito;

/**
 * Libera una retención activa: las unidades vuelven a estar disponibles.
 * No toca el ledger (nunca se consumieron).
 */
class LiberarRetencion
{
    public function ejecutar(RetencionCredito $retencion): void
    {
        if ($retencion->estado === EstadoRetencion::Activa) {
            $retencion->update(['estado' => EstadoRetencion::Liberada]);
        }
    }
}
