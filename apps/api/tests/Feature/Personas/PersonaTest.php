<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('crea una persona con perfil miembro y la lista filtrando por perfil', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/personas', [
        'nombre' => 'Ana',
        'apellidos' => 'Rios',
        'perfiles' => ['miembro'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Ana')
        ->assertJsonPath('data.perfiles.0', 'miembro');

    // El propietario ya tiene una persona, pero sin perfil miembro.
    $respuesta = $this->getJson('/api/v1/personas?perfil=miembro')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(1);
    expect($respuesta->json('data.0.nombre'))->toBe('Ana');
});

it('muestra el detalle de una persona con sus perfiles', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $ulid = $this->postJson('/api/v1/personas', [
        'nombre' => 'Ana',
        'perfiles' => ['miembro', 'cliente'],
    ])->json('data.id');

    $respuesta = $this->getJson("/api/v1/personas/{$ulid}")->assertOk();

    expect($respuesta->json('data.nombre'))->toBe('Ana');
    expect($respuesta->json('data.perfiles'))->toContain('miembro', 'cliente');
});

it('prohibe crear persona sin el permiso miembros.crear', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['miembro']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
