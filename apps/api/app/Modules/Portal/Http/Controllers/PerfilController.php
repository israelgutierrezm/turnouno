<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Portal\Support\MiembroActual;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Perfil del miembro: sus derechos (con saldo) y sus próximas reservas.
 */
class PerfilController
{
    public function __construct(
        private readonly MiembroActual $miembro,
        private readonly LibroMayor $libro,
    ) {}

    public function show(): JsonResponse
    {
        $persona = $this->miembro->persona();

        $derechos = Derecho::query()
            ->whereHas('acuerdo', function (Builder $consulta) use ($persona): void {
                $consulta->where('persona_id', $persona->id);
            })
            ->with('acuerdo.producto')
            ->get();

        $reservas = Reserva::query()
            ->where('persona_id', $persona->id)
            ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::EnEspera->value])
            ->with('sesion.oferta')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'persona' => ['nombre' => trim($persona->nombre.' '.($persona->apellidos ?? ''))],
                'derechos' => $derechos->map(fn (Derecho $derecho): array => [
                    'producto' => $derecho->acuerdo->producto->nombre,
                    'ilimitado' => $derecho->ilimitado,
                    'saldo_creditos' => round($this->libro->saldo($derecho) / 1000, 3),
                    'disponible_creditos' => round($this->libro->disponible($derecho) / 1000, 3),
                ])->all(),
                'reservas' => $reservas->map(fn (Reserva $reserva): array => [
                    'id' => $reserva->ulid,
                    'oferta' => $reserva->sesion->oferta->nombre,
                    'inicia_en' => $reserva->sesion->inicia_en->toIso8601String(),
                    'zona_horaria' => $reserva->sesion->zona_horaria,
                    'estado' => $reserva->estado->value,
                ])->all(),
            ],
        ]);
    }
}
