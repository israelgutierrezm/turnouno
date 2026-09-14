<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/me — el usuario autenticado, su tenant activo y persona, sus
 * pertenencias, y los roles/permisos efectivos en el tenant activo.
 *
 * "pertenencias" = vínculos usuario ↔ tenant (NO son membresías comerciales).
 */
class MeController
{
    public function __invoke(Request $request, TenantContext $context): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenantActual = $context->has() ? $context->tenant() : null;
        $persona = null;
        $roles = [];
        $permisos = [];

        if ($tenantActual instanceof Tenant) {
            $persona = Persona::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantActual->id)
                ->where('user_id', $user->id)
                ->first();

            $roles = $user->getRoleNames()->values()->all();
            $permisos = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return response()->json([
            'usuario' => [
                'id' => $user->ulid,
                'nombre' => $user->name,
                'email' => $user->email,
            ],
            'tenant_actual' => $tenantActual instanceof Tenant ? [
                'id' => $tenantActual->ulid,
                'nombre' => $tenantActual->name,
                'slug' => $tenantActual->slug,
            ] : null,
            'persona' => $persona instanceof Persona ? [
                'id' => $persona->ulid,
                'nombre' => $persona->nombre,
                'apellidos' => $persona->apellidos,
            ] : null,
            'pertenencias' => $user->tenants()->get()->map(static fn (Tenant $tenant): array => [
                'id' => $tenant->ulid,
                'nombre' => $tenant->name,
                'slug' => $tenant->slug,
                'estado' => (string) data_get($tenant, 'pivot.status'),
            ])->all(),
            'roles' => $roles,
            'permisos' => $permisos,
        ]);
    }
}
