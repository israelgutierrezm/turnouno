<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Application;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\Exceptions\SaldoInsuficiente;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Membresias\Models\Derecho;
use Illuminate\Support\Facades\DB;

/**
 * Retiene (hold) unidades de un derecho sin consumirlas aún. Bloquea el derecho
 * para que dos reservas concurrentes no sobre-reserven el último cupo disponible.
 */
class RetenerCreditos
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Derecho $derecho, int $unidades, ?string $descripcion = null): RetencionCredito
    {
        return DB::transaction(function () use ($derecho, $unidades, $descripcion): RetencionCredito {
            $bloqueado = Derecho::query()->whereKey($derecho->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueado->ilimitado && $this->libro->disponible($bloqueado) < $unidades) {
                throw new SaldoInsuficiente('Saldo insuficiente para la retención.');
            }

            return $bloqueado->retenciones()->create([
                'unidades' => $unidades,
                'estado' => EstadoRetencion::Activa,
                'descripcion' => $descripcion,
            ]);
        });
    }
}
