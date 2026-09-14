<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('permite a un usuario cuyo rol de tenant otorga miembros.ver', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/personas')->assertOk();
});

it('prohibe a un usuario cuyo rol de tenant no tiene miembros.ver', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['miembro']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/personas')
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
