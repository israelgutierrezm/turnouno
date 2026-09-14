<?php

declare(strict_types=1);

namespace App\Modules\Autorizacion;

use App\Modules\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el conjunto de roles por defecto de un tenant (team = tenant) y los
 * conecta con sus permisos.
 */
class AprovisionadorDeRoles
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function aprovisionar(Tenant $tenant): void
    {
        $teamPrevio = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($tenant->id);

        try {
            $todos = CatalogoDePermisos::permisos();

            foreach ($todos as $clave) {
                Permission::findOrCreate($clave, 'web');
            }

            foreach (CatalogoDePermisos::roles() as $nombreRol => $permisos) {
                $rol = Role::findOrCreate($nombreRol, 'web');
                $rol->syncPermissions($permisos === ['*'] ? $todos : $permisos);
            }
        } finally {
            $this->registrar->setPermissionsTeamId($teamPrevio);
        }
    }
}
