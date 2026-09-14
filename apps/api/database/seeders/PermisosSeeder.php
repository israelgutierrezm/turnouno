<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Autorizacion\CatalogoDePermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (CatalogoDePermisos::permisos() as $clave) {
            Permission::findOrCreate($clave, 'web');
        }
    }
}
