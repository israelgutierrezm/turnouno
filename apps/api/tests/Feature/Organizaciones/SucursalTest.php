<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Tenancy\Context\TenantContext;
use Laravel\Sanctum\Sanctum;

it('crea una sucursal bajo una organizacion (201)', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $organizacionUlid = $this->postJson('/api/v1/organizaciones', ['nombre' => 'Central'])
        ->assertCreated()
        ->json('data.id');

    $this->postJson('/api/v1/sucursales', [
        'organizacion_id' => $organizacionUlid,
        'nombre' => 'Roma',
        'zona_horaria' => 'America/Mexico_City',
    ])
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Roma')
        ->assertJsonPath('data.zona_horaria', 'America/Mexico_City');
});

it('devuelve 404 al pedir una sucursal de otro tenant', function (): void {
    $tenantA = crearTenant('Tenant A');
    $tenantB = crearTenant('Tenant B');

    $usuarioA = User::factory()->create();
    vincularUsuario($tenantA, $usuarioA, ['propietario']);

    // Creamos una sucursal dentro del tenant B (contexto explicito).
    app(TenantContext::class)->set($tenantB);
    $organizacionB = app(CrearOrganizacion::class)->ejecutar('Org B');
    $sucursalB = app(CrearSucursal::class)->ejecutar($organizacionB, 'Condesa');
    app(TenantContext::class)->clear();

    Sanctum::actingAs($usuarioA);

    $this->getJson('/api/v1/sucursales/'.$sucursalB->ulid)
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});
