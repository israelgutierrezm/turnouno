<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Autorizacion\ControlDeAcceso;
use App\Modules\Tenancy\Models\AsignacionPersonalTenant;
use App\Modules\Tenancy\Models\Usuario;

/**
 * Control de acceso tenant-local con SCOPE por sucursal (R19), portado de
 * {@see ControlDeAcceso}. Un permiso lo concede el rol
 * tenant-wide del usuario (en cualquier sucursal) O, en su ausencia, el rol que el
 * usuario tenga asignado EN esa sucursal (aditivo). Opera sobre la BD del tenant
 * resuelto.
 */
class ResolverAccesoTenant
{
    /**
     * ¿El usuario tiene el permiso dentro de la sucursal dada? Tenant-wide O por rol
     * asignado en esa sucursal.
     */
    public function permiteEnSucursal(Usuario $usuario, string $permiso, int $sucursalId): bool
    {
        return $usuario->puede($permiso) || $this->rolAsignadoPermite($usuario, $permiso, $sucursalId);
    }

    /**
     * ¿El usuario tiene el permiso EN esa sucursal por un rol ASIGNADO ahi (sin contar
     * su rol tenant-wide)? Util para ampliar el alcance de un rol que, tenant-wide,
     * esta acotado (p. ej. un instructor limitado a sus sesiones).
     */
    public function rolAsignadoPermite(Usuario $usuario, string $permiso, int $sucursalId): bool
    {
        $rol = AsignacionPersonalTenant::query()
            ->where('usuario_id', $usuario->getKey())
            ->where('sucursal_id', $sucursalId)
            ->value('rol');

        return is_string($rol) && CatalogoDePermisosTenant::puede($rol, $permiso);
    }

    /**
     * IDs de las sucursales donde el usuario tiene un rol asignado (scope explicito).
     *
     * @return list<int>
     */
    public function sucursalesAsignadas(Usuario $usuario): array
    {
        return AsignacionPersonalTenant::query()
            ->where('usuario_id', $usuario->getKey())
            ->pluck('sucursal_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
