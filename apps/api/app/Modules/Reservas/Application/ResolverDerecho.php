<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Application;

use App\Modules\Agenda\Models\Sesion;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Membresias\EstadoAcuerdo;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Personas\Models\Persona;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Resuelve qué derecho (entitlement) de una persona cubre una sesión: uno
 * vigente y con saldo suficiente. Prefiere gastar un derecho limitado con saldo
 * antes que uno ilimitado, para no "desperdiciar" packs comprados.
 */
class ResolverDerecho
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function paraSesion(Persona $persona, Sesion $sesion, int $unidades): ?Derecho
    {
        $derechos = Derecho::query()
            ->whereHas('acuerdo', function (Builder $consulta) use ($persona): void {
                $consulta->where('persona_id', $persona->id)
                    ->where('estado', EstadoAcuerdo::Activo->value);
            })
            ->get()
            ->filter(fn (Derecho $derecho): bool => $this->vigente($derecho, $sesion->inicia_en));

        $limitado = $derechos->first(
            fn (Derecho $derecho): bool => ! $derecho->ilimitado && $this->libro->disponible($derecho) >= $unidades,
        );

        if ($limitado !== null) {
            return $limitado;
        }

        return $derechos->first(fn (Derecho $derecho): bool => $derecho->ilimitado);
    }

    private function vigente(Derecho $derecho, CarbonInterface $momento): bool
    {
        if ($derecho->valido_desde !== null && $momento->lessThan($derecho->valido_desde)) {
            return false;
        }

        if ($derecho->valido_hasta !== null && $momento->greaterThan($derecho->valido_hasta->copy()->endOfDay())) {
            return false;
        }

        return true;
    }
}
