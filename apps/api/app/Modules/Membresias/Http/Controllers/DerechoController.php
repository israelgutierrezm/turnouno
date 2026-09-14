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

        return response()->json([
            'data' => $derechos->map(function (Derecho $derecho): array {
                $saldo = $this->libro->saldo($derecho);

                return [
                    'id' => $derecho->ulid,
                    'producto' => $derecho->acuerdo->producto->nombre,
                    'ilimitado' => $derecho->ilimitado,
                    'saldo_unidades' => $saldo,
                    'saldo_creditos' => round($saldo / 1000, 3),
                ];
            })->all(),
        ]);
    }
}
