<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Autorizacion\AprovisionadorDeRoles;
use App\Modules\Autorizacion\CatalogoDePermisos;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Crea un tenant y aprovisiona sus roles/permisos por defecto.
 */
class CrearTenant
{
    public function __construct(private readonly AprovisionadorDeRoles $roles) {}

    public function ejecutar(string $nombre, ?string $slug = null): Tenant
    {
        foreach (CatalogoDePermisos::permisos() as $clave) {
            Permission::findOrCreate($clave, 'web');
        }

        // La tabla `tenants` conserva columnas en inglés (término de plataforma).
        $tenant = Tenant::create([
            'name' => $nombre,
            'slug' => $slug ?? Str::slug($nombre).'-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        $this->roles->aprovisionar($tenant);

        return $tenant;
    }
}
