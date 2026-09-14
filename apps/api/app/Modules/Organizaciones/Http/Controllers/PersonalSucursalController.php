<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Http\Controllers;

use App\Models\User;
use App\Modules\Organizaciones\Application\AsignarPersonal;
use App\Modules\Organizaciones\Http\Requests\AsignarPersonalRequest;
use App\Modules\Organizaciones\Models\AsignacionPersonal;
use App\Modules\Organizaciones\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Personal (staff) de una sucursal: listado y alta de asignaciones de rol.
 */
class PersonalSucursalController
{
    public function index(Sucursal $sucursal): JsonResponse
    {
        Gate::authorize('personal.gestionar');

        $asignaciones = $sucursal->asignacionesPersonal()->with(['user', 'role'])->get();

        return response()->json([
            'data' => $asignaciones->map(static fn (AsignacionPersonal $asignacion): array => [
                'id' => $asignacion->ulid,
                'usuario' => [
                    'id' => $asignacion->user->ulid,
                    'nombre' => $asignacion->user->name,
                ],
                'rol' => $asignacion->role->name,
            ])->all(),
        ]);
    }

    public function store(
        AsignarPersonalRequest $request,
        Sucursal $sucursal,
        AsignarPersonal $asignarPersonal,
    ): JsonResponse {
        Gate::authorize('personal.gestionar');

        $user = User::query()
            ->where('ulid', (string) $request->validated('user_id'))
            ->firstOrFail();

        $asignacion = $asignarPersonal->ejecutar($sucursal, $user, (string) $request->validated('rol'));

        return response()->json(['data' => ['id' => $asignacion->ulid]], 201);
    }
}
