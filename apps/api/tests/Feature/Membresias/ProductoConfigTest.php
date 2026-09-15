<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Personas\Models\Persona;
use Laravel\Sanctum\Sanctum;

it('crea un producto recurrente con ciclo/rollover y al venderlo concede el cupo del ciclo', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $respuesta = $this->postJson('/api/v1/productos', [
        'nombre' => 'Mensualidad 8',
        'tipo' => 'membresia',
        'precio_minor' => 99900,
        'moneda' => 'MXN',
        'ilimitado' => false,
        'politica_reset' => 'calendario',
        'unidades_por_ciclo' => 8000,
        'politica_rollover' => 'limitado',
        'rollover_max' => 4000,
    ])->assertCreated();

    $respuesta
        ->assertJsonPath('data.politica_reset', 'calendario')
        ->assertJsonPath('data.unidades_por_ciclo', 8000)
        ->assertJsonPath('data.politica_rollover', 'limitado')
        ->assertJsonPath('data.rollover_max', 4000);

    $productoUlid = $respuesta->json('data.id');
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson("/api/v1/personas/{$persona->ulid}/acuerdos", ['producto_id' => $productoUlid])
        ->assertCreated();

    $derechos = $this->getJson("/api/v1/personas/{$persona->ulid}/derechos")->assertOk();

    // El primer ciclo concede unidades_por_ciclo (no creditos_incluidos).
    expect($derechos->json('data.0.saldo_unidades'))->toBe(8000);
});

it('crea un producto restringido a una actividad y sucursal', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $actividadId = $this->getJson('/api/v1/actividades')->assertOk()->json('data.0.id');
    $sucursalId = $this->getJson('/api/v1/sucursales')->assertOk()->json('data.0.id');

    $this->postJson('/api/v1/productos', [
        'nombre' => 'Pase Pole Centro',
        'tipo' => 'paquete',
        'precio_minor' => 50000,
        'moneda' => 'MXN',
        'ilimitado' => false,
        'creditos_incluidos' => 4000,
        'actividad_id' => $actividadId,
        'sucursal_id' => $sucursalId,
    ])->assertCreated()
        ->assertJsonPath('data.actividad', 'Pole Fitness')
        ->assertJsonPath('data.sucursal', 'Sucursal Centro')
        ->assertJsonPath('data.actividad_id', $actividadId)
        ->assertJsonPath('data.sucursal_id', $sucursalId);
});

it('agrega un top-up a un derecho y sube el saldo disponible', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    ['derecho' => $derecho] = participanteConDerecho($tenant, 8000);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/derechos/{$derecho->ulid}/topups", [
        'unidades' => 2000,
        'descripcion' => 'Cortesia',
    ])->assertCreated()
        ->assertJsonPath('data.saldo_unidades', 10000)
        ->assertJsonPath('data.disponible_unidades', 10000);
});

it('lista las actividades del tenant', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/actividades')
        ->assertOk()
        ->assertJsonPath('data.0.nombre', 'Pole Fitness')
        ->assertJsonPath('data.0.programa', 'Pole');
});
