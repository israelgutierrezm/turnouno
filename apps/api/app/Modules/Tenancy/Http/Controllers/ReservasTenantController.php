<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Reservas\EstadoReserva;
use App\Modules\Tenancy\Application\ReservasTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use App\Modules\Tenancy\Models\Usuario;
use App\Modules\Tenancy\Support\AccesoSesionTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reservas (booking) del estudio, tenant-local: roster de una sesion (confirmadas +
 * lista de espera), crear (motor de booking concurrency-safe) y cancelar (con
 * politica por hold + promocion de lista de espera). Opera SIEMPRE sobre la BD del
 * estudio resuelto.
 */
class ReservasTenantController
{
    public function __construct(
        private readonly ReservasTenant $reservas,
        private readonly AccesoSesionTenant $acceso,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sesion = SesionTenant::query()->where('ulid', (string) $request->route('sesion'))->firstOrFail();

        // Un instructor solo ve el roster de SUS sesiones asignadas.
        $usuario = $request->attributes->get('usuario_tenant');
        abort_unless($this->acceso->puedeOperar($sesion, $usuario instanceof Usuario ? $usuario : null), 403);

        $reservas = ReservaTenant::query()
            ->where('sesion_id', $sesion->getKey())
            ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::Ofrecida->value, EstadoReserva::EnEspera->value])
            ->with(['persona', 'sesion', 'asistencia'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $reservas->map(fn (ReservaTenant $reserva): array => $this->presentar($reserva))->all(),
        ]);
    }

    public function reservar(Request $request): JsonResponse
    {
        $sesion = SesionTenant::query()->where('ulid', (string) $request->route('sesion'))->firstOrFail();

        $validado = $request->validate([
            'persona_id' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'esperar' => ['boolean'],
        ]);

        $persona = PersonaTenant::query()->where('ulid', $validado['persona_id'])->firstOrFail();
        $idempotencyKey = $validado['idempotency_key'] ?? null;

        $reserva = $this->reservas->crear(
            $sesion,
            $persona,
            is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
            (bool) ($validado['esperar'] ?? false),
        );

        return response()->json(['data' => $this->presentar($reserva)], 201);
    }

    /**
     * Evalua una reserva SIN crearla y devuelve la decision estructurada del motor
     * (permitida, reason_code, reglas evaluadas, costo en creditos, derecho a usar,
     * advertencias). Util para mostrar al cliente por que puede/no puede reservar.
     */
    public function preview(Request $request): JsonResponse
    {
        $sesion = SesionTenant::query()->where('ulid', (string) $request->route('sesion'))->firstOrFail();

        $validado = $request->validate([
            'persona_id' => ['required', 'string'],
            'esperar' => ['boolean'],
        ]);

        $persona = PersonaTenant::query()->where('ulid', $validado['persona_id'])->firstOrFail();

        $decision = $this->reservas->evaluar($sesion, $persona, null, (bool) ($validado['esperar'] ?? false));

        return response()->json(['data' => $decision->aArreglo()]);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $reserva = ReservaTenant::query()->where('ulid', (string) $request->route('reserva'))->firstOrFail();

        $this->reservas->cancelar($reserva);

        return response()->json(['data' => $this->presentar($reserva->refresh())]);
    }

    public function aceptar(Request $request): JsonResponse
    {
        $reserva = ReservaTenant::query()->where('ulid', (string) $request->route('reserva'))->firstOrFail();

        $this->reservas->aceptar($reserva);

        return response()->json(['data' => $this->presentar($reserva->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(ReservaTenant $reserva): array
    {
        $reserva->loadMissing(['persona', 'sesion', 'asistencia']);
        $persona = $reserva->persona;

        return [
            'id' => $reserva->ulid,
            'estado' => $reserva->estado->value,
            'persona' => $persona !== null ? trim($persona->nombre.' '.($persona->apellidos ?? '')) : null,
            'inicia_en' => $reserva->sesion?->inicia_en->toIso8601String(),
            'unidades' => $reserva->unidades,
            'asistencia' => $reserva->asistencia?->estado->value,
        ];
    }
}
