<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\RegistrarCheckin;
use App\Modules\Tenancy\Models\CheckinTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use App\Modules\Tenancy\Models\Usuario;
use App\Modules\Tenancy\ProveedorPartner;
use App\Modules\Tenancy\Support\AccesoSesionTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Check-ins de plataformas de bienestar (Wellhub / TotalPass) en las clases del
 * estudio. Registrar valida el codigo del usuario contra el proveedor y guarda el
 * acceso (sin consumir creditos). Un instructor solo opera sus sesiones asignadas.
 */
class CheckinsTenantController
{
    public function __construct(
        private readonly RegistrarCheckin $registrar,
        private readonly AccesoSesionTenant $acceso,
    ) {}

    public function registrar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'proveedor' => ['required', Rule::in(ProveedorPartner::valores())],
            'sesion_id' => ['required', 'string'],
            'codigo' => ['required', 'string', 'max:255'],
        ]);

        $sesion = SesionTenant::query()->where('ulid', $validado['sesion_id'])->firstOrFail();
        $usuario = $request->attributes->get('usuario_tenant');
        abort_unless($this->acceso->puedeOperar($sesion, $usuario instanceof Usuario ? $usuario : null), 403);

        $checkin = $this->registrar->ejecutar($sesion, $validado['proveedor'], $validado['codigo']);

        return response()->json(['data' => $this->presentar($checkin)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $sesion = SesionTenant::query()->where('ulid', (string) $request->route('sesion'))->firstOrFail();
        $usuario = $request->attributes->get('usuario_tenant');
        abort_unless($this->acceso->puedeOperar($sesion, $usuario instanceof Usuario ? $usuario : null), 403);

        $checkins = CheckinTenant::query()
            ->where('sesion_id', $sesion->getKey())
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $checkins->map(fn (CheckinTenant $c): array => $this->presentar($c))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(CheckinTenant $checkin): array
    {
        return [
            'id' => $checkin->ulid,
            'proveedor' => $checkin->proveedor,
            'usuario' => $checkin->nombre_usuario,
            'estado' => $checkin->estado,
            'registrado_en' => $checkin->registrado_en->toIso8601String(),
        ];
    }
}
