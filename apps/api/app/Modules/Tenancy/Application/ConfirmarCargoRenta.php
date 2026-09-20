<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\EstadoCargoRenta;
use App\Modules\Tenancy\Models\CargoRenta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Confirma un cargo de renta pendiente a partir de la referencia del intento (la que
 * envia el webhook de la pasarela de la plataforma) y lo marca `pagado`.
 * Idempotente: un cargo que no esta pendiente no se reprocesa.
 */
class ConfirmarCargoRenta
{
    public function porReferencia(string $referencia, string $proveedor): void
    {
        if ($referencia === '') {
            return;
        }

        DB::transaction(function () use ($referencia, $proveedor): void {
            $cargo = CargoRenta::query()
                ->where('referencia_pago', $referencia)
                ->lockForUpdate()
                ->first();

            if (! $cargo instanceof CargoRenta || $cargo->estado !== EstadoCargoRenta::Pendiente) {
                return;
            }

            $cargo->update([
                'estado' => EstadoCargoRenta::Pagado->value,
                'pagado_en' => Carbon::now(),
                'metodo_pago' => $proveedor,
            ]);
        });
    }
}
