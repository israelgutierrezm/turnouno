<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('configura y activa una pasarela sin exponer las llaves', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $this->putJson('/api/v1/pasarelas/stripe', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => ['public_key' => 'pk_test_visible', 'secret_key' => 'sk_test_SECRETO'],
    ])
        ->assertOk()
        ->assertJsonPath('data.activa', true)
        // Se listan los NOMBRES de llave, nunca los valores.
        ->assertJsonPath('data.llaves_configuradas', ['public_key', 'secret_key'])
        ->assertDontSee('sk_test_SECRETO');

    $this->getJson('/api/v1/pasarelas')
        ->assertOk()
        ->assertDontSee('sk_test_SECRETO');
});

it('guarda las credenciales cifradas en la base', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $this->putJson('/api/v1/pasarelas/stripe', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => ['secret_key' => 'sk_test_SECRETO'],
    ])->assertOk();

    $crudo = DB::table('configuraciones_pasarela')->where('proveedor', 'stripe')->value('credenciales');
    expect($crudo)->toBeString();
    expect($crudo)->not->toContain('sk_test_SECRETO'); // cifrado en reposo
});

it('una pasarela en linea activa habilita el cobro pendiente confirmado por webhook', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $this->putJson('/api/v1/pasarelas/stripe', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => ['secret_key' => 'sk_test_x'],
    ])->assertOk();

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    // Cobro en línea: queda PENDIENTE (asíncrono), la orden aún no se paga.
    $referencia = $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'stripe', 'metodo' => 'tarjeta'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente')
        ->assertJsonPath('data.orden_estado', 'pendiente')
        ->json('data.referencia');

    // Aún sin derecho.
    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(0, 'data');

    // El webhook confirma → fulfillment.
    $this->postJson('/api/v1/webhooks/pagos/stripe', ['referencia' => $referencia, 'estado' => 'aprobado'])->assertOk();

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('no permite cobrar con una pasarela inactiva', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    // Stripe no está configurada/activa → no es una pasarela válida.
    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'stripe'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');
});

it('exige el permiso pagos.configurar', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['recepcionista']); // no tiene pagos.configurar

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/pasarelas/stripe', ['activa' => true, 'modo' => 'test'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
