<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Recursos\Models\Instalacion;
use App\Modules\Tenancy\Context\TenantContext;
use Laravel\Sanctum\Sanctum;

it('crea instalacion y recursos con jerarquia y los muestra', function (): void {
    $tenant = crearTenant('AquaKids');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    app(TenantContext::class)->set($tenant);
    $organizacion = app(CrearOrganizacion::class)->ejecutar('Central');
    $sucursal = app(CrearSucursal::class)->ejecutar($organizacion, 'Coyoacan');
    app(TenantContext::class)->clear();

    Sanctum::actingAs($user);

    $instalacionUlid = $this->postJson("/api/v1/sucursales/{$sucursal->ulid}/instalaciones", [
        'nombre' => 'Alberca A',
    ])->assertCreated()->json('data.id');

    $albercaUlid = $this->postJson("/api/v1/instalaciones/{$instalacionUlid}/recursos", [
        'nombre' => 'Alberca',
        'modo' => 'unidad',
        'capacidad' => 1,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/instalaciones/{$instalacionUlid}/recursos", [
        'nombre' => 'Carril 1',
        'modo' => 'unidad',
        'recurso_padre_id' => $albercaUlid,
    ])->assertCreated();

    $respuesta = $this->getJson("/api/v1/instalaciones/{$instalacionUlid}")->assertOk();

    expect($respuesta->json('data.recursos'))->toHaveCount(2);

    $carril = collect($respuesta->json('data.recursos'))->firstWhere('nombre', 'Carril 1');
    expect($carril['padre']['nombre'])->toBe('Alberca');
});

it('devuelve 404 al pedir una instalacion de otro tenant', function (): void {
    $tenantA = crearTenant('Tenant A');
    $tenantB = crearTenant('Tenant B');

    $usuarioA = User::factory()->create();
    vincularUsuario($tenantA, $usuarioA, ['propietario']);

    app(TenantContext::class)->set($tenantB);
    $organizacionB = app(CrearOrganizacion::class)->ejecutar('Org B');
    $sucursalB = app(CrearSucursal::class)->ejecutar($organizacionB, 'Sur');
    $instalacionB = Instalacion::create(['sucursal_id' => $sucursalB->id, 'nombre' => 'Alberca B']);
    app(TenantContext::class)->clear();

    Sanctum::actingAs($usuarioA);

    $this->getJson("/api/v1/instalaciones/{$instalacionB->ulid}")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});
