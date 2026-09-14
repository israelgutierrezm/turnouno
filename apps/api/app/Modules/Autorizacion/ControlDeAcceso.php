<?php

declare(strict_types=1);

namespace App\Modules\Autorizacion;

use App\Models\User;
use App\Modules\Organizaciones\Models\AsignacionPersonal;
use App\Modules\Organizaciones\Models\Sucursal;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Control de acceso con scope. Complementa a spatie (roles tenant-wide) con el
 * scope por sucursal (ADR-0008): un permiso tenant-wide aplica en cualquier
 * sucursal; en su ausencia se evalúa el rol asignado en esa sucursal.
 */
class ControlDeAcceso
{
    /**
     * Permiso a nivel tenant (usa spatie con el team activo del request).
     */
    public function permite(User $user, string $permiso): bool
    {
        return $user->can($permiso);
    }

    /**
     * Permiso dentro de una sucursal concreta.
     */
    public function permiteEnSucursal(User $user, string $permiso, Sucursal $sucursal): bool
    {
        if ($user->can($permiso)) {
            return true;
        }

        return AsignacionPersonal::query()
            ->where('user_id', $user->id)
            ->where('sucursal_id', $sucursal->id)
            ->whereHas('role.permissions', function (Builder $query) use ($permiso): void {
                $query->where('name', $permiso);
            })
            ->exists();
    }
}
