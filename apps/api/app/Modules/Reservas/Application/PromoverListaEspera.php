<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Application;

use App\Modules\Agenda\Models\Sesion;
use App\Modules\Creditos\Application\RetenerCreditos;
use App\Modules\Creditos\Exceptions\SaldoInsuficiente;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Models\Reserva;

/**
 * Promueve al siguiente en la lista de espera (FIFO) cuando se libera un cupo.
 * Debe llamarse DENTRO de una transacción con la sesión ya bloqueada
 * (`lockForUpdate`), para no promover más allá de la capacidad.
 */
class PromoverListaEspera
{
    private const UNIDADES_POR_SESION = 1000;

    public function __construct(private readonly RetenerCreditos $retener) {}

    public function ejecutar(Sesion $sesion): ?Reserva
    {
        if ($sesion->capacidad === null) {
            return null;
        }

        $confirmadas = Reserva::query()
            ->where('sesion_id', $sesion->id)
            ->where('estado', EstadoReserva::Confirmada->value)
            ->count();

        if ($confirmadas >= $sesion->capacidad) {
            return null;
        }

        $siguiente = Reserva::query()
            ->where('sesion_id', $sesion->id)
            ->where('estado', EstadoReserva::EnEspera->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($siguiente === null) {
            return null;
        }

        $derecho = $siguiente->derecho;
        $retencion = null;
        $unidadesReservadas = 0;

        if (! $derecho->ilimitado) {
            try {
                $retencion = $this->retener->ejecutar($derecho, self::UNIDADES_POR_SESION, 'Promoción de lista de espera');
                $unidadesReservadas = self::UNIDADES_POR_SESION;
            } catch (SaldoInsuficiente) {
                // Sin crédito al promover: se queda en espera para intentar luego.
                return null;
            }
        }

        $siguiente->update([
            'estado' => EstadoReserva::Confirmada->value,
            'retencion_id' => $retencion?->id,
            'unidades' => $unidadesReservadas,
        ]);

        return $siguiente;
    }
}
