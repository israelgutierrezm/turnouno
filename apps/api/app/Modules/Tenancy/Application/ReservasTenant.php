<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Creditos\Exceptions\SaldoInsuficiente;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Exceptions\CupoLleno;
use App\Modules\Reservas\Exceptions\FueraDeVentana;
use App\Modules\Reservas\Exceptions\ReservaException;
use App\Modules\Reservas\Exceptions\SesionNoReservable;
use App\Modules\Reservas\Exceptions\SinDerechoDisponible;
use App\Modules\Reservas\Exceptions\YaReservado;
use App\Modules\Tenancy\EstadoSesionTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use Illuminate\Database\QueryException;
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

        // Pre-check con el motor: decision estructurada + fail-fast. Traduce el codigo
        // estable a la excepcion de dominio. La transaccion re-valida lo critico bajo
        // lock (concurrencia). Ver evaluar() y docs/BOOKING_ENGINE.md.
        $decision = $this->evaluar($sesion, $persona, $costo, $permitirEspera);
        if (! $decision->permitida) {
            throw $this->excepcionDe((string) $decision->codigo, (string) $decision->mensaje);
        }

        try {
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
                        'costo_unidades' => $costo,
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
                    'costo_unidades' => $costo,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (QueryException $e) {
            // Carrera de idempotencia: otra peticion concurrente ya creo la reserva
            // con la misma clave (viola el unique). Se devuelve la existente, no un 500.
            if ($idempotencyKey !== null) {
                $previa = ReservaTenant::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($previa !== null) {
                    return $previa;
                }
            }

            throw $e;
        }
    }

    /**
     * Evalua (SIN crear ni bloquear) si una persona puede reservar una sesion y
     * devuelve una DECISION estructurada y explicable (para el endpoint de preview y
     * la salida del motor). `crear()` la reutiliza como pre-check y luego re-valida lo
     * critico bajo lock. Ver docs/BOOKING_ENGINE.md.
     *
     * @param  int|null  $unidades  costo en unidades escaladas (por defecto 1 credito)
     */
    public function evaluar(SesionTenant $sesion, PersonaTenant $persona, ?int $unidades = null, bool $permitirEspera = false): DecisionReserva
    {
        $costo = $unidades ?? self::UNIDADES_POR_SESION;
        $reglas = [];

        $reglas['sesion_programada'] = $programada = $sesion->estado === EstadoSesionTenant::Programada;
        if (! $programada) {
            return DecisionReserva::rechazar('SESSION_NOT_BOOKABLE', 'La sesion no admite reservas.', $reglas);
        }

        $reglas['dentro_de_ventana'] = $enVentana = ! $sesion->inicia_en->isPast();
        if (! $enVentana) {
            return DecisionReserva::rechazar('BOOKING_NOT_OPEN', 'La sesion ya inicio.', $reglas);
        }

        $duplicada = ReservaTenant::query()
            ->where('sesion_id', $sesion->getKey())
            ->where('persona_id', $persona->getKey())
            ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::EnEspera->value])
            ->exists();
        $reglas['sin_reserva_previa'] = ! $duplicada;
        if ($duplicada) {
            return DecisionReserva::rechazar('ALREADY_BOOKED', 'Ya existe una reserva para esta sesion.', $reglas);
        }

        $derecho = $this->resolver->paraSesion($persona, $sesion, $costo);
        $reglas['derecho_disponible'] = $derecho !== null;
        if ($derecho === null) {
            return DecisionReserva::rechazar('ENTITLEMENT_REQUIRED', 'No hay un derecho con saldo para esta sesion.', $reglas);
        }

        $llena = $sesion->capacidad !== null && $this->confirmadas($sesion) >= $sesion->capacidad;
        $reglas['con_cupo'] = ! $llena;
        $advertencias = [];
        if ($llena) {
            if (! $permitirEspera) {
                return DecisionReserva::rechazar('CAPACITY_FULL', 'La sesion esta llena.', $reglas);
            }
            $advertencias[] = 'WAITLIST';
        }

        // El hold solo se toma para un derecho limitado y con cupo (la lista de espera
        // no retiene credito hasta que se promueve).
        $costoCreditos = ($derecho->ilimitado || $llena) ? 0 : $costo;

        return DecisionReserva::permitir($reglas, $costoCreditos, $derecho->ulid, $advertencias);
    }

    /**
     * Traduce el codigo estable de una decision rechazada a su excepcion de dominio
     * (que `ApiExceptionRenderer` mapea al contrato de error de la API).
     */
    private function excepcionDe(string $codigo, string $mensaje): ReservaException
    {
        return match ($codigo) {
            'BOOKING_NOT_OPEN' => new FueraDeVentana($mensaje),
            'ALREADY_BOOKED' => new YaReservado($mensaje),
            'ENTITLEMENT_REQUIRED' => new SinDerechoDisponible($mensaje),
            'CAPACITY_FULL' => new CupoLleno($mensaje),
            default => new SesionNoReservable($mensaje),
        };
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
        // Consume el costo REAL con el que se creo la reserva (no un valor fijo).
        $costo = $siguiente->costo_unidades ?? self::UNIDADES_POR_SESION;

        if ($derecho !== null && ! $derecho->ilimitado) {
            try {
                $retencion = $this->creditos->retener($derecho, $costo, 'Promocion de lista de espera');
                $unidadesReservadas = $costo;
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

    /**
     * Cancela una sesion (cancelacion del NEGOCIO): marca la sesion como cancelada y
     * cancela TODAS sus reservas activas (confirmadas + en espera) liberando sus holds
     * — el credito retenido vuelve al miembro, sin penalizarlo. Atomico (bloquea la
     * sesion) e idempotente. No promueve lista de espera: la sesion no ocurrira.
     */
    public function cancelarSesion(SesionTenant $sesion): void
    {
        DB::connection('tenant')->transaction(function () use ($sesion): void {
            $bloqueada = SesionTenant::query()->whereKey($sesion->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado === EstadoSesionTenant::Cancelada) {
                return;
            }

            $reservas = ReservaTenant::query()
                ->where('sesion_id', $bloqueada->getKey())
                ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::EnEspera->value])
                ->with('retencion')
                ->lockForUpdate()
                ->get();

            foreach ($reservas as $reserva) {
                if ($reserva->retencion !== null) {
                    $this->creditos->liberar($reserva->retencion);
                }

                $reserva->update(['estado' => EstadoReserva::Cancelada->value]);
            }

            $bloqueada->update(['estado' => EstadoSesionTenant::Cancelada->value]);
        });
    }

    private function confirmadas(SesionTenant $sesion): int
    {
        return ReservaTenant::query()
            ->where('sesion_id', $sesion->getKey())
            ->where('estado', EstadoReserva::Confirmada->value)
            ->count();
    }
}
