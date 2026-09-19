<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Reservas\EstadoReserva;
use App\Modules\Tenancy\Application\LibroMayorTenant;
use App\Modules\Tenancy\Application\ReservasTenant;
use App\Modules\Tenancy\Application\WaiversTenant;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use App\Modules\Tenancy\Models\Usuario;
use App\Modules\Tenancy\Models\WaiverTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autoservicio del miembro (data plane del tenant): opera SOLO sobre la persona del
 * usuario autenticado. La persona se resuelve por `usuario_id`, o por correo (enlace
 * diferido la primera vez). Sin permisos especiales: cada quien ve/gestiona lo suyo.
 */
class MiTenantController
{
    public function __construct(
        private readonly ReservasTenant $reservas,
        private readonly LibroMayorTenant $libro,
        private readonly WaiversTenant $waivers,
    ) {}

    /**
     * Waivers/consentimientos que el miembro tiene pendientes de aceptar (incluye
     * re-aceptacion cuando el estudio publica una version nueva).
     */
    public function waiversPendientes(Request $request): JsonResponse
    {
        $persona = $this->persona($request);
        if (! $persona instanceof PersonaTenant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->waivers->pendientesDe($persona)->map(fn (WaiverTenant $w): array => [
                'id' => $w->ulid,
                'clave' => $w->clave,
                'titulo' => $w->titulo,
                'contenido' => $w->contenido,
                'version' => $w->version,
            ])->all(),
        ]);
    }

    public function aceptarWaiver(Request $request): JsonResponse
    {
        $persona = $this->persona($request);
        abort_unless($persona instanceof PersonaTenant, 403);

        $waiver = WaiverTenant::query()->where('ulid', (string) $request->route('waiver'))->firstOrFail();
        $aceptacion = $this->waivers->aceptar($persona, $waiver, $request->ip());

        return response()->json(['data' => [
            'waiver' => $waiver->ulid,
            'aceptado_en' => $aceptacion->aceptado_en->toIso8601String(),
        ]], 201);
    }

    public function perfil(Request $request): JsonResponse
    {
        $persona = $this->persona($request);

        if (! $persona instanceof PersonaTenant) {
            return response()->json(['data' => ['persona' => null, 'derechos' => [], 'reservas' => []]]);
        }

        $derechos = DerechoTenant::query()
            ->whereHas('acuerdo', fn ($q) => $q->where('persona_id', $persona->getKey()))
            ->get()
            ->map(fn (DerechoTenant $d): array => [
                'id' => $d->ulid,
                'ilimitado' => $d->ilimitado,
                'saldo' => $d->ilimitado ? null : $this->libro->saldo($d),
                'disponible' => $d->ilimitado ? null : $this->libro->disponible($d),
            ])->all();

        $reservas = ReservaTenant::query()
            ->where('persona_id', $persona->getKey())
            ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::Ofrecida->value, EstadoReserva::EnEspera->value])
            ->with(['sesion.oferta'])
            ->get()
            ->filter(fn (ReservaTenant $r): bool => $r->sesion !== null && ! $r->sesion->inicia_en->isPast())
            ->map(fn (ReservaTenant $r): array => $this->presentarReserva($r))
            ->values()->all();

        return response()->json(['data' => [
            'persona' => ['nombre' => $persona->nombreCompleto(), 'email' => $persona->email],
            'derechos' => $derechos,
            'reservas' => $reservas,
        ]]);
    }

    public function agenda(Request $request): JsonResponse
    {
        $sesiones = SesionTenant::query()
            ->where('estado', 'programada')
            ->where('inicia_en', '>=', CarbonImmutable::now())
            ->with('oferta')
            ->orderBy('inicia_en')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $sesiones->map(fn (SesionTenant $s): array => [
                'id' => $s->ulid,
                'oferta' => $s->oferta?->nombre,
                'inicia_en' => $s->inicia_en->toIso8601String(),
                'zona_horaria' => $s->zona_horaria,
                'capacidad' => $s->capacidad,
            ])->all(),
        ]);
    }

    public function reservar(Request $request): JsonResponse
    {
        $persona = $this->persona($request);
        abort_unless($persona instanceof PersonaTenant, 403, 'No tienes un perfil de miembro en este estudio.');

        $validado = $request->validate([
            'sesion_id' => ['required', 'string'],
            'esperar' => ['boolean'],
        ]);

        $sesion = SesionTenant::query()->where('ulid', $validado['sesion_id'])->firstOrFail();
        $reserva = $this->reservas->crear($sesion, $persona, null, (bool) ($validado['esperar'] ?? false));

        return response()->json(['data' => $this->presentarReserva($reserva->load('sesion.oferta'))], 201);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $persona = $this->persona($request);
        abort_unless($persona instanceof PersonaTenant, 403);

        $reserva = ReservaTenant::query()->where('ulid', (string) $request->route('reserva'))->firstOrFail();
        abort_unless((int) $reserva->persona_id === (int) $persona->getKey(), 403, 'Esta reserva no es tuya.');

        $this->reservas->cancelar($reserva);

        return response()->json(['data' => $this->presentarReserva($reserva->refresh()->load('sesion.oferta'))]);
    }

    public function aceptar(Request $request): JsonResponse
    {
        $persona = $this->persona($request);
        abort_unless($persona instanceof PersonaTenant, 403);

        $reserva = ReservaTenant::query()->where('ulid', (string) $request->route('reserva'))->firstOrFail();
        abort_unless((int) $reserva->persona_id === (int) $persona->getKey(), 403, 'Esta reserva no es tuya.');

        $this->reservas->aceptar($reserva);

        return response()->json(['data' => $this->presentarReserva($reserva->refresh()->load('sesion.oferta'))]);
    }

    /**
     * Resuelve la persona del usuario autenticado; enlaza por correo la primera vez.
     */
    private function persona(Request $request): ?PersonaTenant
    {
        $usuario = $request->attributes->get('usuario_tenant');
        if (! $usuario instanceof Usuario) {
            return null;
        }

        $persona = PersonaTenant::query()->where('usuario_id', $usuario->getKey())->first();
        if ($persona instanceof PersonaTenant) {
            return $persona;
        }

        // Enlace diferido: una persona sin usuario con el mismo correo.
        $porCorreo = PersonaTenant::query()
            ->whereNull('usuario_id')
            ->where('email', $usuario->email)
            ->first();
        if ($porCorreo instanceof PersonaTenant) {
            $porCorreo->update(['usuario_id' => $usuario->getKey()]);
        }

        return $porCorreo;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarReserva(ReservaTenant $reserva): array
    {
        return [
            'id' => $reserva->ulid,
            'estado' => $reserva->estado->value,
            'oferta' => $reserva->sesion?->oferta?->nombre,
            'inicia_en' => $reserva->sesion?->inicia_en->toIso8601String(),
            'zona_horaria' => $reserva->sesion?->zona_horaria,
        ];
    }
}
