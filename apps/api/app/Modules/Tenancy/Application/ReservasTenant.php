<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Creditos\Exceptions\SaldoInsuficiente;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Exceptions\CupoLleno;
use App\Modules\Reservas\Exceptions\FueraDeVentana;
use App\Modules\Reservas\Exceptions\SesionNoReservable;
use App\Modules\Reservas\Exceptions\SinDerechoDisponible;
use App\Modules\Reservas\Exceptions\YaReservado;
use App\Modules\Tenancy\EstadoSesionTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use Illuminate\Support\Facades\DB;

/**
 * Motor de reserva tenant-local (ver docs/BOOKING_ENGINE.md). Valida ventana,
 * estado de la sesion, duplicados y capacidad; resuelve un derecho y coloca una
 * retencion (hold); todo dentro de una transaccion con `lockForUpdate` sobre la
 * sesion (en la conexion del tenant) para que dos reservas concurrentes no
 * sobrepasen la capacidad (invariante de no-sobreventa).
 */
class ReservasTenant
{
    // Costo por defecto de una sesion: 1 credito = 1000 unidades escaladas.
    private const UNIDADES_POR_SESION = 1000;

    public function __construct(
        private readonly ResolverDerechoTenant $resolver,
        private readonly CreditosTenant $creditos,
    ) {}

    public function crear(SesionTenant $sesion, PersonaTenant $persona, ?string $idempotencyKey = null, bool $permitirEspera = false, ?int $unidades = null): ReservaTenant
    {
        $costo = $unidades ?? self::UNIDADES_POR_SESION;

        // Reintento idempotente: la misma clave devuelve la reserva ya creada.
        if ($idempotencyKey !== null) {
            $previa = ReservaTenant::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($previa !== null) {
                return $previa;
            }
        }

        if ($sesion->estado !== EstadoSesionTenant::Programada) {
            throw new SesionNoReservable('La sesion no admite reservas.');
        }

        if ($sesion->inicia_en->isPast()) {
            throw new FueraDeVentana('La sesion ya inicio.');
        }

        return DB::connection('tenant')->transaction(function () use ($sesion, $persona, $idempotencyKey, $permitirEspera, $costo): ReservaTenant {
            $bloqueada = SesionTenant::query()->whereKey($sesion->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado !== EstadoSesionTenant::Programada) {
                throw new SesionNoReservable('La sesion no admite reservas.');
            }

            $activa = ReservaTenant::query()
                ->where('sesion_id', $bloqueada->getKey())
                ->where('persona_id', $persona->getKey())
                ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::EnEspera->value])
                ->exists();

            if ($activa) {
                throw new YaReservado('Ya existe una reserva para esta sesion.');
            }

            $derecho = $this->resolver->paraSesion($persona, $bloqueada, $costo);

            if ($derecho === null) {
                throw new SinDerechoDisponible('No hay un derecho con saldo para esta sesion.');
            }

            // Si hay cupo definido y esta lleno: lista de espera (sin hold) o rechazo.
            if ($bloqueada->capacidad !== null && $this->confirmadas($bloqueada) >= $bloqueada->capacidad) {
                if (! $permitirEspera) {
                    throw new CupoLleno('La sesion esta llena.');
                }

                return ReservaTenant::query()->create([
                    'sesion_id' => $bloqueada->getKey(),
                    'persona_id' => $persona->getKey(),
                    'derecho_id' => $derecho->getKey(),
                    'retencion_id' => null,
                    'estado' => EstadoReserva::EnEspera->value,
                    'unidades' => 0,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            $retencion = null;
            $unidadesReservadas = 0;

            if (! $derecho->ilimitado) {
                $retencion = $this->creditos->retener($derecho, $costo, 'Reserva de sesion');
                $unidadesReservadas = $costo;
            }

            return ReservaTenant::query()->create([
                'sesion_id' => $bloqueada->getKey(),
                'persona_id' => $persona->getKey(),
                'derecho_id' => $derecho->getKey(),
                'retencion_id' => $retencion?->getKey(),
                'estado' => EstadoReserva::Confirmada->value,
                'unidades' => $unidadesReservadas,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Cancela una reserva aplicando la politica por hold: a tiempo (a mas de
     * `horasLimite` del inicio) libera la retencion y el credito vuelve; tarde, la
     * confirma (penaliza). Al liberar un cupo confirmado, promueve de la lista de
     * espera. Idempotente: cancelar una reserva ya cancelada no hace nada.
     */
    public function cancelar(ReservaTenant $reserva, int $horasLimite = 6): ReservaTenant
    {
        return DB::connection('tenant')->transaction(function () use ($reserva, $horasLimite): ReservaTenant {
            $bloqueada = ReservaTenant::query()->whereKey($reserva->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado === EstadoReserva::Cancelada) {
                return $bloqueada;
            }

            // Cancelar un lugar en lista de espera: no hay hold ni promocion.
            if ($bloqueada->estado === EstadoReserva::EnEspera) {
                $bloqueada->update(['estado' => EstadoReserva::Cancelada->value]);

                return $bloqueada;
            }

            // Bloquea la sesion para promover de forma segura tras liberar el cupo.
            $sesion = SesionTenant::query()->whereKey($bloqueada->sesion_id)->lockForUpdate()->firstOrFail();

            $retencion = $bloqueada->retencion;
            if ($retencion !== null) {
                $momentoLimite = $sesion->inicia_en->copy()->subHours($horasLimite);

                if (now()->lessThanOrEqualTo($momentoLimite)) {
                    $this->creditos->liberar($retencion);
                } else {
                    $this->creditos->confirmar($retencion);
                }
            }

            $bloqueada->update(['estado' => EstadoReserva::Cancelada->value]);

            $this->promover($sesion);

            return $bloqueada;
        });
    }

    /**
     * Promueve al siguiente en la lista de espera (FIFO) cuando se libera un cupo.
     * Debe llamarse DENTRO de una transaccion con la sesion ya bloqueada.
     */
    public function promover(SesionTenant $sesion): ?ReservaTenant
    {
        if ($sesion->capacidad === null) {
            return null;
        }

        if ($this->confirmadas($sesion) >= $sesion->capacidad) {
            return null;
        }

        $siguiente = ReservaTenant::query()
            ->where('sesion_id', $sesion->getKey())
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

        if ($derecho !== null && ! $derecho->ilimitado) {
            try {
                $retencion = $this->creditos->retener($derecho, self::UNIDADES_POR_SESION, 'Promocion de lista de espera');
                $unidadesReservadas = self::UNIDADES_POR_SESION;
            } catch (SaldoInsuficiente) {
                // Sin credito al promover: se queda en espera para intentar luego.
                return null;
            }
        }

        $siguiente->update([
            'estado' => EstadoReserva::Confirmada->value,
            'retencion_id' => $retencion?->getKey(),
            'unidades' => $unidadesReservadas,
        ]);

        return $siguiente;
    }

    private function confirmadas(SesionTenant $sesion): int
    {
        return ReservaTenant::query()
            ->where('sesion_id', $sesion->getKey())
            ->where('estado', EstadoReserva::Confirmada->value)
            ->count();
    }
}
