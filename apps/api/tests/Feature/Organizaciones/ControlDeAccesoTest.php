<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Autorizacion\ControlDeAcceso;
use App\Modules\Organizaciones\Application\AsignarPersonal;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Tenancy\Context\TenantContext;
use Spatie\Permission\PermissionRegistrar;

it('un rol tenant-wide permite el permiso en cualquier sucursal', function (): void {
    $tenant = crearTenant('Acme');
    $propietario = User::factory()->create();
    vincularUsuario($tenant, $propietario, ['propietario']);

    app(TenantContext::class)->set($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $organizacion = app(CrearOrganizacion::class)->ejecutar('Central');
    $sucursal = app(CrearSucursal::class)->ejecutar($organizacion, 'Roma');

    $control = app(ControlDeAcceso::class);

    expect($control->permiteEnSucursal($propietario, 'personal.gestionar', $sucursal))->toBeTrue();
});

it('un rol con scope de sucursal permite solo en su sucursal', function (): void {
    $tenant = crearTenant('Acme');
    $recepcionista = User::factory()->create();
    // Sin rol tenant-wide: solo tendra acceso donde se le asigne personal.
    vincularUsuario($tenant, $recepcionista, []);

    app(TenantContext::class)->set($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $organizacion = app(CrearOrganizacion::class)->ejecutar('Central');
    $roma = app(CrearSucursal::class)->ejecutar($organizacion, 'Roma');
    $condesa = app(CrearSucursal::class)->ejecutar($organizacion, 'Condesa');

    app(AsignarPersonal::class)->ejecutar($roma, $recepcionista, 'recepcionista');

    $control = app(ControlDeAcceso::class);

    expect($control->permiteEnSucursal($recepcionista, 'miembros.ver', $roma))->toBeTrue();
    expect($control->permiteEnSucursal($recepcionista, 'miembros.ver', $condesa))->toBeFalse();
});
