<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Application;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Creditos\Application\RetenerCreditos;
use App\Modules\Personas\Models\Persona;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Exceptions\CupoLleno;
use App\Modules\Reservas\Exceptions\FueraDeVentana;
use App\Modules\Reservas\Exceptions\SesionNoReservable;
use App\Modules\Reservas\Exceptions\SinDerechoDisponible;
use App\Modules\Reservas\Exceptions\YaReservado;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Motor de reserva (ver BOOKING_ENGINE.md). Valida ventana, estado de la sesión,
 * duplicados y capacidad; resuelve un derecho y coloca una retención (hold); todo
 * dentro de una transacción con `lockForUpdate` sobre la sesión para que dos
 * reservas concurrentes no sobrepasen la capacidad (invariante de no-sobreventa).
 */
class CrearReserva
{
    // Costo por defecto de una sesión: 1 crédito = 1000 unidades escaladas.
    private const UNIDADES_POR_SESION = 1000;

    public function __construct(
        private readonly ResolverDerecho $resolver,
        private readonly RetenerCreditos $retener,
    ) {}

    public function ejecutar(Sesion $sesion, Persona $persona, ?string $idempotencyKey = null, ?int $unidades = null): Reserva
    {
        $costo = $unidades ?? self::UNIDADES_POR_SESION;

        // Reintento idempotente: la misma clave devuelve la reserva ya creada.
        if ($idempotencyKey !== null) {
            $previa = Reserva::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($previa !== null) {
                return $previa;
            }
        }

        if ($sesion->estado !== EstadoSesion::Programada) {
            throw new SesionNoReservable('La sesión no admite reservas.');
        }

        if ($sesion->inicia_en->isPast()) {
            throw new FueraDeVentana('La sesión ya inició.');
        }

        return DB::transaction(function () use ($sesion, $persona, $idempotencyKey, $costo): Reserva {
            $bloqueada = Sesion::query()->whereKey($sesion->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado !== EstadoSesion::Programada) {
                throw new SesionNoReservable('La sesión no admite reservas.');
            }

            $yaReservado = Reserva::query()
                ->where('sesion_id', $bloqueada->id)
                ->where('persona_id', $persona->id)
                ->where('estado', EstadoReserva::Confirmada->value)
                ->exists();

            if ($yaReservado) {
                throw new YaReservado('Ya existe una reserva para esta sesión.');
            }

            if ($bloqueada->capacidad !== null) {
                $confirmadas = Reserva::query()
                    ->where('sesion_id', $bloqueada->id)
                    ->where('estado', EstadoReserva::Confirmada->value)
                    ->count();

                if ($confirmadas >= $bloqueada->capacidad) {
                    throw new CupoLleno('La sesión está llena.');
                }
            }

            $derecho = $this->resolver->paraSesion($persona, $bloqueada, $costo);

            if ($derecho === null) {
                throw new SinDerechoDisponible('No hay un derecho con saldo para esta sesión.');
            }

            $retencion = null;
            $unidadesReservadas = 0;

            if (! $derecho->ilimitado) {
                $retencion = $this->retener->ejecutar($derecho, $costo, 'Reserva de sesión');
                $unidadesReservadas = $costo;
            }

            return Reserva::create([
                'sesion_id' => $bloqueada->id,
                'persona_id' => $persona->id,
                'derecho_id' => $derecho->id,
                'retencion_id' => $retencion?->id,
                'estado' => EstadoReserva::Confirmada->value,
                'unidades' => $unidadesReservadas,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
