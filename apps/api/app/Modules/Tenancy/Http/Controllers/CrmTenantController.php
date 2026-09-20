<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Crm\EtapaProspecto;
use App\Modules\Crm\OrigenProspecto;
use App\Modules\Crm\TipoActividadProspecto;
use App\Modules\Tenancy\Application\GestionarCrmTenant;
use App\Modules\Tenancy\Models\ProspectoActividadTenant;
use App\Modules\Tenancy\Models\ProspectoTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRM comercial del estudio, tenant-local (R15): embudo de prospectos (leads) con
 * etapas, responsable, seguimiento, bitácora de interacciones y conversión a miembro.
 * Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class CrmTenantController
{
    public function __construct(private readonly GestionarCrmTenant $crm) {}

    public function index(Request $request): JsonResponse
    {
        $consulta = ProspectoTenant::query()->with(['responsable', 'persona', 'sucursal']);

        $etapa = (string) $request->query('etapa', '');
        if ($etapa !== '' && EtapaProspecto::tryFrom($etapa) !== null) {
            $consulta->where('etapa', $etapa);
        }
        $origen = (string) $request->query('origen', '');
        if ($origen !== '' && OrigenProspecto::tryFrom($origen) !== null) {
            $consulta->where('origen', $origen);
        }
        $responsable = (string) $request->query('responsable_id', '');
        if ($responsable !== '') {
            $id = Usuario::query()->where('ulid', $responsable)->value('id');
            $consulta->where('responsable_id', $id !== null ? (int) $id : 0);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $consulta->where(function ($sub) use ($q): void {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
            });
        }

        $prospectos = $consulta->orderByDesc('id')->get();

        // Conteo por etapa (embudo completo, sin los filtros de etapa) para el tablero.
        $pipeline = ProspectoTenant::query()
            ->selectRaw('etapa, count(*) as total')
            ->groupBy('etapa')
            ->pluck('total', 'etapa');

        return response()->json([
            'data' => $prospectos->map(fn (ProspectoTenant $p): array => $this->presentar($p))->all(),
            'pipeline' => collect(EtapaProspecto::cases())
                ->mapWithKeys(fn (EtapaProspecto $e): array => [$e->value => (int) ($pipeline[$e->value] ?? 0)])
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $prospecto = $this->crm->crear($datos, $this->actor($request));

        return response()->json(['data' => $this->presentar($prospecto->load(['responsable', 'persona', 'sucursal']))], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $prospecto = $this->resolver($request);

        return response()->json(['data' => $this->presentar($prospecto, conActividades: true)]);
    }

    public function actualizar(Request $request): JsonResponse
    {
        $prospecto = $this->resolver($request);
        $datos = $this->validar($request);

        $this->crm->actualizar($prospecto, $datos);

        return response()->json(['data' => $this->presentar($prospecto->load(['responsable', 'persona', 'sucursal']))]);
    }

    public function cambiarEtapa(Request $request): JsonResponse
    {
        $prospecto = $this->resolver($request);
        $validado = $request->validate([
            'etapa' => ['required', Rule::enum(EtapaProspecto::class)],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $etapa = EtapaProspecto::from($validado['etapa']);
        $this->crm->cambiarEtapa($prospecto, $etapa, $validado['motivo'] ?? null, $this->actor($request));

        return response()->json(['data' => $this->presentar($prospecto->fresh(['responsable', 'persona', 'sucursal']) ?? $prospecto, conActividades: true)]);
    }

    public function actividad(Request $request): JsonResponse
    {
        $prospecto = $this->resolver($request);
        $validado = $request->validate([
            'tipo' => ['required', Rule::enum(TipoActividadProspecto::class)],
            'detalle' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->crm->registrarActividad(
            $prospecto,
            TipoActividadProspecto::from($validado['tipo']),
            $validado['detalle'] ?? null,
            $this->actor($request),
        );

        return response()->json(['data' => $this->presentar($prospecto, conActividades: true)]);
    }

    public function convertir(Request $request): JsonResponse
    {
        $prospecto = $this->resolver($request);

        if ($prospecto->etapa === EtapaProspecto::Perdido) {
            throw ValidationException::withMessages(['etapa' => ['No se puede convertir un prospecto perdido.']]);
        }

        $persona = $this->crm->convertir($prospecto, $this->actor($request));

        return response()->json([
            'data' => $this->presentar($prospecto->fresh(['responsable', 'persona', 'sucursal']) ?? $prospecto, conActividades: true),
            'miembro' => ['id' => $persona->ulid, 'nombre' => $persona->nombreCompleto()],
        ], 201);
    }

    private function resolver(Request $request): ProspectoTenant
    {
        return ProspectoTenant::query()->where('ulid', (string) $request->route('prospecto'))->firstOrFail();
    }

    private function actor(Request $request): ?Usuario
    {
        $usuario = $request->attributes->get('usuario_tenant');

        return $usuario instanceof Usuario ? $usuario : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'origen' => ['nullable', Rule::enum(OrigenProspecto::class)],
            'interes' => ['nullable', 'string', 'max:255'],
            'responsable_id' => ['nullable', 'string'],
            'sucursal_id' => ['nullable', 'string'],
            'proximo_seguimiento' => ['nullable', 'date'],
        ]);

        return [
            'nombre' => $validado['nombre'],
            'email' => $validado['email'] ?? null,
            'telefono' => $validado['telefono'] ?? null,
            'origen' => $validado['origen'] ?? OrigenProspecto::Otro->value,
            'interes' => $validado['interes'] ?? null,
            'responsable_id' => $this->idPorUlid(Usuario::class, $validado['responsable_id'] ?? null),
            'sucursal_id' => $this->idPorUlid(SucursalTenant::class, $validado['sucursal_id'] ?? null),
            'proximo_seguimiento' => $validado['proximo_seguimiento'] ?? null,
        ];
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    private function idPorUlid(string $modelo, ?string $ulid): ?int
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }

        $id = $modelo::query()->where('ulid', $ulid)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(ProspectoTenant $prospecto, bool $conActividades = false): array
    {
        $datos = [
            'id' => $prospecto->ulid,
            'nombre' => $prospecto->nombre,
            'email' => $prospecto->email,
            'telefono' => $prospecto->telefono,
            'origen' => $prospecto->origen->value,
            'etapa' => $prospecto->etapa->value,
            'interes' => $prospecto->interes,
            'motivo' => $prospecto->motivo,
            'proximo_seguimiento' => $prospecto->proximo_seguimiento?->toDateString(),
            'convertido_en' => $prospecto->convertido_en?->toIso8601String(),
            'responsable' => $prospecto->responsable?->name,
            'responsable_id' => $prospecto->responsable?->ulid,
            'miembro' => $prospecto->persona !== null
                ? ['id' => $prospecto->persona->ulid, 'nombre' => $prospecto->persona->nombreCompleto()]
                : null,
            'creado_en' => $prospecto->created_at?->toIso8601String(),
        ];

        if ($conActividades) {
            $datos['actividades'] = $prospecto->actividades()
                ->with('usuario')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ProspectoActividadTenant $a): array => [
                    'id' => $a->ulid,
                    'tipo' => $a->tipo->value,
                    'detalle' => $a->detalle,
                    'usuario' => $a->usuario?->name,
                    'creado_en' => $a->created_at?->toIso8601String(),
                ])->all();
        }

        return $datos;
    }
}
