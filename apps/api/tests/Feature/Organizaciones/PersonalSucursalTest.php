<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Tenancy\Context\TenantContext;
use Laravel\Sanctum\Sanctum;

it('asigna personal a una sucursal y lo lista', function (): void {
    $tenant = crearTenant('Acme');
    $propietario = User::factory()->create();
    vincularUsuario($tenant, $propietario, ['propietario']);

    $empleado = User::factory()->create(['name' => 'Empleado Uno']);
    vincularUsuario($tenant, $empleado, []);

    app(TenantContext::class)->set($tenant);
    $organizacion = app(CrearOrganizacion::class)->ejecutar('Central');
    $sucursal = app(CrearSucursal::class)->ejecutar($organizacion, 'Roma');
    app(TenantContext::class)->clear();

    Sanctum::actingAs($propietario);

    $this->postJson("/api/v1/sucursales/{$sucursal->ulid}/personal", [
        'user_id' => $empleado->ulid,
        'rol' => 'recepcionista',
    ])->assertCreated();

    $this->getJson("/api/v1/sucursales/{$sucursal->ulid}/personal")
        ->assertOk()
        ->assertJsonPath('data.0.rol', 'recepcionista')
        ->assertJsonPath('data.0.usuario.nombre', 'Empleado Uno');
});

it('lista los usuarios del tenant activo', function (): void {
    $tenant = crearTenant('Acme');
    $propietario = User::factory()->create();
    vincularUsuario($tenant, $propietario, ['propietario']);

    $otro = User::factory()->create();
    vincularUsuario($tenant, $otro, []);

    Sanctum::actingAs($propietario);

    $respuesta = $this->getJson('/api/v1/usuarios')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(2);
});
