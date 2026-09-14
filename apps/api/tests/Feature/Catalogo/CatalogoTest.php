<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('crea programa, actividad, nivel y oferta y los muestra anidados', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $programaUlid = $this->postJson('/api/v1/programas', ['nombre' => 'Pole'])
        ->assertCreated()
        ->json('data.id');

    $actividadUlid = $this->postJson("/api/v1/programas/{$programaUlid}/actividades", [
        'nombre' => 'Pole Fitness',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/actividades/{$actividadUlid}/niveles", [
        'nombre' => 'Principiante',
        'orden' => 1,
    ])->assertCreated();

    $this->postJson("/api/v1/actividades/{$actividadUlid}/ofertas", [
        'nombre' => 'Clase grupal',
        'modalidad' => 'grupal',
        'capacidad' => 8,
    ])->assertCreated()->assertJsonPath('data.modalidad', 'grupal');

    $respuesta = $this->getJson("/api/v1/programas/{$programaUlid}")->assertOk();

    expect($respuesta->json('data.actividades'))->toHaveCount(1);
    expect($respuesta->json('data.actividades.0.niveles'))->toHaveCount(1);
    expect($respuesta->json('data.actividades.0.ofertas.0.capacidad'))->toBe(8);
});

it('lista las ofertas del tenant con su actividad', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    ['oferta' => $oferta] = crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/ofertas')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $oferta->ulid)
        ->assertJsonPath('data.0.actividad', 'Pole Fitness');
});

it('prohibe crear programa sin el permiso catalogo.gestionar', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['miembro']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/programas', ['nombre' => 'Pole'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
