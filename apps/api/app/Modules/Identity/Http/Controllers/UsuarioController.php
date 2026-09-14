<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Usuarios que pertenecen al tenant activo. Sirve, por ejemplo, para poblar el
 * selector al asignar personal a una sucursal.
 */
class UsuarioController
{
    public function index(TenantContext $context): JsonResponse
    {
        Gate::authorize('personal.gestionar');

        $usuarios = $context->tenant()->users()->orderBy('name')->get();

        return response()->json([
            'data' => $usuarios->map(static fn (User $usuario): array => [
                'id' => $usuario->ulid,
                'nombre' => $usuario->name,
                'email' => $usuario->email,
            ])->all(),
        ]);
    }
}
