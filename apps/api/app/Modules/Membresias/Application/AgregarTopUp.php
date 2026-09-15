<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Application;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\Models\MovimientoCredito;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\Models\Derecho;

/**
 * Agrega un add-on / top-up a un derecho: un asiento adicional en el ledger, sin
 * editar la membresía original (MEMBERSHIP_ENGINE.md: "never edit… create a
 * separate grant and ledger entries").
 */
class AgregarTopUp
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Derecho $derecho, int $unidades, ?string $descripcion = null): MovimientoCredito
    {
        return $this->libro->registrar(
            $derecho,
            TipoMovimiento::AddOn,
            $unidades,
            $descripcion ?? 'Add-on / top-up',
        );
    }
}
