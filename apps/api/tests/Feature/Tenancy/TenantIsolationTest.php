<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Personas\Models\Persona;
use Laravel\Sanctum\Sanctum;

it('devuelve 404 al pedir una persona de otro tenant', function (): void {
    $tenantA = crearTenant('Tenant A');
    $tenantB = crearTenant('Tenant B');

    $usuarioA = User::factory()->create();
    vincularUsuario($tenantA, $usuarioA, ['propietario']);

    $personaB = Persona::factory()->create(['tenant_id' => $tenantB->id]);

    Sanctum::actingAs($usuarioA);

    $this->getJson('/api/v1/personas/'.$personaB->ulid)
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('prohibe seleccionar un tenant del que no eres miembro', function (): void {
    $tenantA = crearTenant('Tenant A');
    $tenantB = crearTenant('Tenant B');

    $usuarioA = User::factory()->create();
    vincularUsuario($tenantA, $usuarioA, ['propietario']);

    Sanctum::actingAs($usuarioA);

    $this->withHeader('X-Tenant-ID', $tenantB->ulid)
        ->getJson('/api/v1/me')
        ->assertStatus(403)
        ->assertJsonPath('code', 'TENANT_FORBIDDEN');
});

it('lista solo las personas del tenant activo', function (): void {
    $tenantA = crearTenant('Tenant A');
    $tenantB = crearTenant('Tenant B');

    $usuarioA = User::factory()->create();
    vincularUsuario($tenantA, $usuarioA, ['propietario']); // crea 1 persona (el propietario)

    Persona::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    Persona::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    Sanctum::actingAs($usuarioA);

    $response = $this->getJson('/api/v1/personas')->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});
