<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Application;

use App\Models\User;
use App\Modules\Organizaciones\Models\AsignacionPersonal;
use App\Modules\Organizaciones\Models\Sucursal;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Asigna a un usuario un rol dentro de una sucursal (scope por sucursal, ADR-0008).
 */
class AsignarPersonal
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function ejecutar(Sucursal $sucursal, User $user, string $nombreRol): AsignacionPersonal
    {
        // El rol pertenece al team (tenant) de la sucursal.
        $teamPrevio = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($sucursal->tenant_id);

        try {
            $rol = Role::findOrCreate($nombreRol, 'web');
        } finally {
            $this->registrar->setPermissionsTeamId($teamPrevio);
        }

        return AsignacionPersonal::updateOrCreate([
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'role_id' => $rol->id,
        ]);
    }
}
