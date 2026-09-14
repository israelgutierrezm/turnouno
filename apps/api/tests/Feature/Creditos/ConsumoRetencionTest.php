<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Creditos\LibroMayor;
use Laravel\Sanctum\Sanctum;

it('consume creditos reduciendo saldo y disponible', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    $derecho = crearDerechoConCreditos($tenant, 8000);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/derechos/{$derecho->ulid}/consumos", ['unidades' => 3000])
        ->assertCreated();

    $libro = app(LibroMayor::class);
    expect($libro->saldo($derecho))->toBe(5000);
    expect($libro->disponible($derecho))->toBe(5000);
});

it('rechaza el consumo por saldo insuficiente', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    $derecho = crearDerechoConCreditos($tenant, 8000);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/derechos/{$derecho->ulid}/consumos", ['unidades' => 9000])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SALDO_INSUFICIENTE');

    expect(app(LibroMayor::class)->saldo($derecho))->toBe(8000);
});

it('retiene reduciendo disponible sin tocar el saldo, y libera', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    $derecho = crearDerechoConCreditos($tenant, 8000);

    Sanctum::actingAs($user);

    $retencionUlid = $this->postJson("/api/v1/derechos/{$derecho->ulid}/retenciones", ['unidades' => 2000])
        ->assertCreated()
        ->json('data.id');

    $libro = app(LibroMayor::class);
    expect($libro->saldo($derecho))->toBe(8000);
    expect($libro->disponible($derecho))->toBe(6000);

    $this->postJson("/api/v1/retenciones/{$retencionUlid}/liberar")->assertOk();

    expect($libro->disponible($derecho))->toBe(8000);
});

it('confirma una retencion consumiendo del ledger', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    $derecho = crearDerechoConCreditos($tenant, 8000);

    Sanctum::actingAs($user);

    $retencionUlid = $this->postJson("/api/v1/derechos/{$derecho->ulid}/retenciones", ['unidades' => 2000])
        ->json('data.id');

    $this->postJson("/api/v1/retenciones/{$retencionUlid}/confirmar")->assertOk();

    $libro = app(LibroMayor::class);
    expect($libro->saldo($derecho))->toBe(6000);
    expect($libro->disponible($derecho))->toBe(6000);
});

it('no permite sobre-retener el ultimo cupo disponible', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    $derecho = crearDerechoConCreditos($tenant, 8000);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/derechos/{$derecho->ulid}/retenciones", ['unidades' => 8000])
        ->assertCreated();

    $this->postJson("/api/v1/derechos/{$derecho->ulid}/retenciones", ['unidades' => 1000])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SALDO_INSUFICIENTE');
});
