<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Http;

use App\Modules\Reservas\Models\Reserva;

/**
 * Da forma a la representación JSON de una reserva (incluye su asistencia si existe).
 */
class ReservaPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(Reserva $reserva): array
    {
        $reserva->loadMissing(['persona', 'sesion', 'asistencia']);

        return [
            'id' => $reserva->ulid,
            'estado' => $reserva->estado->value,
            'persona' => trim($reserva->persona->nombre.' '.($reserva->persona->apellidos ?? '')),
            'inicia_en' => $reserva->sesion->inicia_en->toIso8601String(),
            'unidades' => $reserva->unidades,
            'asistencia' => $reserva->asistencia?->estado->value,
        ];
    }
}
