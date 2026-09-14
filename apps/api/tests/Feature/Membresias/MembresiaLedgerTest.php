<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Personas\Models\Persona;
use Laravel\Sanctum\Sanctum;

it('vende una membresia limitada y concede creditos en el ledger', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $productoUlid = $this->postJson('/api/v1/productos', [
        'nombre' => 'Pole 8 clases',
        'tipo' => 'membresia',
        'precio_minor' => 89900,
        'moneda' => 'MXN',
        'ilimitado' => false,
        'creditos_incluidos' => 8000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.precio_minor', 89900)
        ->json('data.id');

    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson("/api/v1/personas/{$persona->ulid}/acuerdos", ['producto_id' => $productoUlid])
        ->assertCreated();

    $respuesta = $this->getJson("/api/v1/personas/{$persona->ulid}/derechos")->assertOk();

    expect($respuesta->json('data'))->toHaveCount(1);
    expect($respuesta->json('data.0.saldo_unidades'))->toBe(8000);
    expect($respuesta->json('data.0.saldo_creditos'))->toEqual(8.0);
    expect($respuesta->json('data.0.ilimitado'))->toBeFalse();
});

it('vende una membresia ilimitada sin asientos en el ledger', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $productoUlid = $this->postJson('/api/v1/productos', [
        'nombre' => 'Ilimitada',
        'tipo' => 'membresia',
        'precio_minor' => 129900,
        'moneda' => 'MXN',
        'ilimitado' => true,
    ])->assertCreated()->json('data.id');

    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson("/api/v1/personas/{$persona->ulid}/acuerdos", ['producto_id' => $productoUlid])
        ->assertCreated();

    $respuesta = $this->getJson("/api/v1/personas/{$persona->ulid}/derechos")->assertOk();

    expect($respuesta->json('data.0.ilimitado'))->toBeTrue();
    expect($respuesta->json('data.0.saldo_unidades'))->toBe(0);
});

it('prohibe vender sin el permiso membresias.gestionar', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['miembro']);

    Sanctum::actingAs($user);

    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson("/api/v1/personas/{$persona->ulid}/acuerdos", ['producto_id' => 'no-importa'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
