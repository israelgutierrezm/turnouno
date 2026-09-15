<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Controllers;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Personas\Models\Persona;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Derechos (entitlements) de una persona, con su saldo derivado del ledger.
 */
class DerechoController
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function index(Persona $persona): JsonResponse
    {
        Gate::authorize('membresias.ver');

        $derechos = Derecho::query()
            ->whereHas('acuerdo', function (Builder $consulta) use ($persona): void {
                $consulta->where('persona_id', $persona->id);
            })
            ->with('acuerdo.producto')
            ->orderBy('id')
            ->get();

        // Saldos en 2 consultas agregadas en vez de 3 por derecho (F-17).
        $proyeccion = $this->libro->proyeccion($derechos->pluck('id')->all());

        return response()->json([
            'data' => $derechos->map(function (Derecho $derecho) use ($proyeccion): array {
                $saldo = $proyeccion[$derecho->id]['saldo'] ?? 0;
                $disponible = $proyeccion[$derecho->id]['disponible'] ?? 0;

                return [
                    'id' => $derecho->ulid,
                    'producto' => $derecho->acuerdo->producto->nombre,
                    'ilimitado' => $derecho->ilimitado,
                    'saldo_unidades' => $saldo,
                    'saldo_creditos' => round($saldo / 1000, 3),
                    'disponible_unidades' => $disponible,
                    'disponible_creditos' => round($disponible / 1000, 3),
                ];
            })->all(),
        ]);
    }
}
