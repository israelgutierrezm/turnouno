<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Creditos\OrigenMovimiento;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Exceptions\ReservaNoConfirmada;
use App\Modules\Tenancy\Models\AsistenciaTenant as ModeloAsistenciaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Registra (o re-marca) la asistencia tenant-local de una reserva confirmada y
 * LIQUIDA su retencion exactamente una vez: `presente` consume el credito (servicio
 * prestado), `ausente` lo pierde (no-show). Las liquidaciones solo actuan sobre una
 * retencion activa, de modo que re-marcar es idempotente y no cobra dos veces. Un
 * derecho ilimitado no tiene retencion y solo registra la asistencia.
 */
class AsistenciaTenant
{
    public function __construct(private readonly CreditosTenant $creditos) {}

    public function marcar(ReservaTenant $reserva, EstadoAsistencia $estado, ?Usuario $actor = null): ModeloAsistenciaTenant
    {
        if ($reserva->estado !== EstadoReserva::Confirmada) {
            throw new ReservaNoConfirmada('Solo se registra asistencia de reservas confirmadas.');
        }

        return DB::connection('tenant')->transaction(function () use ($reserva, $estado, $actor): ModeloAsistenciaTenant {
            $asistencia = ModeloAsistenciaTenant::query()->updateOrCreate(
                ['reserva_id' => $reserva->getKey()],
                ['estado' => $estado->value, 'registrada_en' => now()],
            );

            $retencion = $reserva->retencion;

            if ($retencion !== null) {
                // La liquidación del hold es una operación que mueve crédito: se deja
                // trazable con el origen (reserva), la reserva referida y quién marcó.
                $contexto = ContextoMovimiento::para(
                    OrigenMovimiento::Reserva,
                    'reserva',
                    $reserva->ulid,
                    $actor,
                    ['asistencia' => $estado->value],
                );

                match ($estado) {
                    EstadoAsistencia::Presente => $this->creditos->confirmar($retencion, $contexto),
                    EstadoAsistencia::Ausente => $this->creditos->perder($retencion, $contexto),
                };
            }

            return $asistencia;
        });
    }
}
