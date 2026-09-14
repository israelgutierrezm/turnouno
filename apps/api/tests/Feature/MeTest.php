<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('devuelve el usuario, tenant activo, persona, roles y permisos', function (): void {
    $tenant = crearTenant('Acme Pole');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('usuario.id', $user->ulid)
        ->assertJsonPath('usuario.email', $user->email)
        ->assertJsonPath('tenant_actual.id', $tenant->ulid)
        ->assertJsonPath('persona.nombre', $user->name);

    expect($response->json('roles'))->toContain('propietario');
    expect($response->json('permisos'))->toContain('miembros.ver');
    expect($response->json('pertenencias'))->toHaveCount(1);
});

it('deja el tenant activo sin resolver cuando la pertenencia es ambigua', function (): void {
    $primero = crearTenant('Estudio Uno');
    $segundo = crearTenant('Estudio Dos');
    $user = User::factory()->create();
    vincularUsuario($primero, $user, ['miembro']);
    vincularUsuario($segundo, $user, ['miembro']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me')->assertOk();

    expect($response->json('tenant_actual'))->toBeNull();
    expect($response->json('pertenencias'))->toHaveCount(2);
    expect($response->json('permisos'))->toBe([]);
});
