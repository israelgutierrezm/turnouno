<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Laravel\Sanctum\Sanctum;

/**
 * Da créditos a la persona de un miembro (pack de `$unidades`).
 */
function darCreditosAMiembro(Tenant $tenant, Persona $persona, int $unidades): void
{
    app(TenantContext::class)->set($tenant);
    $producto = app(CrearProducto::class)->ejecutar('Pack', TipoProducto::Paquete, 0, 'MXN', false, $unidades);
    app(CrearAcuerdo::class)->ejecutar($persona, $producto);
    app(TenantContext::class)->clear();
}

it('el miembro ve su perfil con derechos', function (): void {
    $tenant = crearTenant('Pole House');
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    darCreditosAMiembro($tenant, personaDe($miembro), 8000);

    Sanctum::actingAs($miembro);

    $this->getJson('/api/v1/mi/perfil')
        ->assertOk()
        ->assertJsonCount(1, 'data.derechos')
        ->assertJsonPath('data.derechos.0.saldo_creditos', 8);
});

it('el miembro reserva su propia sesion', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);

    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    darCreditosAMiembro($tenant, personaDe($miembro), 8000);

    Sanctum::actingAs($miembro);

    $this->postJson('/api/v1/mi/reservas', ['sesion_id' => $sesion->ulid])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'confirmada');

    // Aparece en su perfil.
    $this->getJson('/api/v1/mi/perfil')->assertOk()->assertJsonCount(1, 'data.reservas');
});

it('el miembro cancela su propia reserva', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);

    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    darCreditosAMiembro($tenant, personaDe($miembro), 8000);

    Sanctum::actingAs($miembro);

    $reserva = $this->postJson('/api/v1/mi/reservas', ['sesion_id' => $sesion->ulid])
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/mi/reservas/{$reserva}/cancelar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelada');
});

it('el miembro no puede cancelar la reserva de otra persona', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);

    $otro = User::factory()->create();
    vincularUsuario($tenant, $otro, ['miembro']);
    darCreditosAMiembro($tenant, personaDe($otro), 8000);
    Sanctum::actingAs($otro);
    $reservaAjena = $this->postJson('/api/v1/mi/reservas', ['sesion_id' => $sesion->ulid])
        ->assertCreated()->json('data.id');

    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    Sanctum::actingAs($miembro);

    $this->postJson("/api/v1/mi/reservas/{$reservaAjena}/cancelar")->assertStatus(403);
});

it('el miembro compra un producto y lo paga (manual) y recibe el derecho', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    $producto = crearProductoPack2($tenant);

    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);

    Sanctum::actingAs($miembro);

    $orden = $this->postJson('/api/v1/mi/ordenes', ['producto_id' => $producto])
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/mi/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])
        ->assertCreated()
        ->assertJsonPath('data.orden_estado', 'pagada');

    $this->getJson('/api/v1/mi/perfil')->assertOk()->assertJsonCount(1, 'data.derechos');
});

it('el miembro ve su historial de compras (solo las suyas)', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    $producto = crearProductoPack2($tenant);

    // Compra de otro miembro (no debe verse).
    $otro = User::factory()->create();
    vincularUsuario($tenant, $otro, ['miembro']);
    Sanctum::actingAs($otro);
    $ordenAjena = $this->postJson('/api/v1/mi/ordenes', ['producto_id' => $producto])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/mi/ordenes/{$ordenAjena}/pagos", ['proveedor' => 'manual'])->assertCreated();

    // El miembro hace su propia compra.
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    Sanctum::actingAs($miembro);
    $orden = $this->postJson('/api/v1/mi/ordenes', ['producto_id' => $producto])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/mi/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])->assertCreated();

    $this->getJson('/api/v1/mi/ordenes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado', 'pagada')
        ->assertJsonPath('data.0.pagos.0.estado', 'aprobado');
});

it('mi/pasarelas devuelve las llaves publicas sin secretos', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    Sanctum::actingAs($owner);
    $this->putJson('/api/v1/pasarelas/stripe', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => ['public_key' => 'pk_visible', 'secret_key' => 'sk_SECRETO'],
    ])->assertOk();

    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    Sanctum::actingAs($miembro);

    $respuesta = $this->getJson('/api/v1/mi/pasarelas')->assertOk()->assertDontSee('sk_SECRETO');

    $stripe = collect($respuesta->json('data'))->firstWhere('proveedor', 'stripe');
    expect($stripe['llaves']['public_key'])->toBe('pk_visible');
    expect($stripe['llaves'])->not->toHaveKey('secret_key');
});

/**
 * Crea un producto pack directamente en el tenant (sin sesión autenticada).
 */
function crearProductoPack2(Tenant $tenant): string
{
    app(TenantContext::class)->set($tenant);
    $producto = app(CrearProducto::class)->ejecutar('Pack 8', TipoProducto::Paquete, 89900, 'MXN', false, 8000);
    app(TenantContext::class)->clear();

    return $producto->ulid;
}
