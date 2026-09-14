<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('crea una organizacion (201) y la lista aislada por tenant', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/organizaciones', ['nombre' => 'Central'])
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Central');

    $respuesta = $this->getJson('/api/v1/organizaciones')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(1);
});

it('prohibe crear organizacion sin el permiso organizaciones.gestionar', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['miembro']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/organizaciones', ['nombre' => 'Central'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
