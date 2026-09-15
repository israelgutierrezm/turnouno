<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\Models\Acuerdo;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Ordenes\Application\CrearOrden;
use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Pagos\Application\CobrarOrden;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\PasarelaDePago;
use App\Modules\Pagos\Pasarelas\ResultadoPago;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use Laravel\Sanctum\Sanctum;

it('crea una orden pendiente con el total correcto', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana', 'apellidos' => 'Ríos'])
        ->assertCreated()->json('data.id');

    $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto, 'cantidad' => 2]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente')
        ->assertJsonPath('data.total_minor', 179800)
        ->assertJsonPath('data.moneda', 'MXN');
});

it('rechaza una orden que mezcla monedas (F-18)', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $mxn = crearProductoPack(); // MXN
    $usd = $this->postJson('/api/v1/productos', [
        'nombre' => 'Pase USD', 'tipo' => 'paquete', 'precio_minor' => 1000, 'moneda' => 'USD',
        'ilimitado' => false, 'creditos_incluidos' => 1000,
    ])->assertCreated()->json('data.id');
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $mxn], ['producto_id' => $usd]],
    ])->assertStatus(422)->assertJsonPath('code', 'MIXED_CURRENCY');
});

it('limita la cantidad por item de una orden (F-18)', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto, 'cantidad' => 9999]],
    ])->assertStatus(422);
});

it('cobra una orden (manual) y concede el derecho al comprador', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');

    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'aprobado')
        ->assertJsonPath('data.orden_estado', 'pagada');

    // Fulfillment: el comprador ahora tiene un derecho con 8 créditos.
    $this->getJson("/api/v1/personas/{$persona}/derechos")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.saldo_creditos', 8);
});

it('concede el derecho al beneficiario cuando difiere del comprador', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    // La madre compra; la hija (dependiente) es la beneficiaria.
    $madre = $this->postJson('/api/v1/personas', ['nombre' => 'María'])->assertCreated()->json('data.id');
    $hija = $this->postJson("/api/v1/personas/{$madre}/dependientes", ['nombre' => 'Sofía'])
        ->assertCreated()->json('data.id');

    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $madre,
        'items' => [['producto_id' => $producto, 'beneficiario_id' => $hija]],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'simulada'])->assertCreated();

    // El derecho está en la hija, no en la madre.
    $this->getJson("/api/v1/personas/{$hija}/derechos")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/personas/{$madre}/derechos")->assertOk()->assertJsonCount(0, 'data');
});

it('el cobro es idempotente por idempotency_key (no concede dos veces)', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $primero = $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual', 'idempotency_key' => 'pago-1'])
        ->assertCreated()->json('data.id');
    $segundo = $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual', 'idempotency_key' => 'pago-1'])
        ->assertCreated()->json('data.id');

    expect($segundo)->toBe($primero);
    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('cobrar una orden ya pagada no vuelve a conceder', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])->assertCreated();
    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])
        ->assertCreated()
        ->assertJsonPath('data.orden_estado', 'pagada');

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('un pago rechazado deja la orden pendiente y no concede derecho', function (): void {
    $tenant = crearTenant('Pole House');
    app(TenantContext::class)->set($tenant);

    $producto = app(CrearProducto::class)->ejecutar('Pack', TipoProducto::Paquete, 89900, 'MXN', false, 8000);
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    $orden = app(CrearOrden::class)->ejecutar($persona, [
        ['producto' => $producto, 'cantidad' => 1],
    ]);

    $pasarelaRechazo = new class implements PasarelaDePago
    {
        public function nombre(): string
        {
            return 'rechazo-test';
        }

        public function cobrar(Pago $pago): ResultadoPago
        {
            return ResultadoPago::rechazado('Fondos insuficientes');
        }
    };

    $pago = app(CobrarOrden::class)->ejecutar($orden, $pasarelaRechazo);

    expect($pago->estado->value)->toBe('rechazado');
    expect($orden->refresh()->estado)->toBe(EstadoOrden::Pendiente);
    expect(Acuerdo::where('persona_id', $persona->id)->count())->toBe(0);

    app(TenantContext::class)->clear();
});

it('exige el permiso pagos.crear para cobrar', function (): void {
    $tenant = crearTenant('Acme');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    $sinPagos = User::factory()->create();
    vincularUsuario($tenant, $sinPagos, ['miembro']); // sin pagos.crear

    Sanctum::actingAs($owner);
    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($sinPagos);
    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('rechaza una pasarela desconocida', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    $producto = crearProductoPack();
    $persona = $this->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = $this->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'inexistente'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');
});

/**
 * Cobra una orden nueva y devuelve [pago_ulid, persona_ulid, derecho_ulid].
 *
 * @return array{pago: string, persona: string, derecho: string}
 */
function cobrarOrdenNueva(): array
{
    $producto = crearProductoPack();
    $persona = test()->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = test()->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $pago = test()->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'manual'])
        ->assertCreated()->json('data.id');

    $derecho = test()->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->json('data.0.id');

    return ['pago' => $pago, 'persona' => $persona, 'derecho' => $derecho];
}

it('reembolsa un pago aprobado revirtiendo el derecho intacto', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    ['pago' => $pago, 'persona' => $persona] = cobrarOrdenNueva();

    $this->postJson("/api/v1/pagos/{$pago}/reembolso")
        ->assertOk()
        ->assertJsonPath('data.estado', 'reembolsado')
        ->assertJsonPath('data.orden_estado', 'cancelada');

    // El derecho quedó revertido a 0.
    $this->getJson("/api/v1/personas/{$persona}/derechos")
        ->assertOk()
        ->assertJsonPath('data.0.saldo_creditos', 0);
});

it('lista las ordenes con comprador y sus pagos, y reembolsa desde el listado', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    cobrarOrdenNueva();

    $listado = $this->getJson('/api/v1/ordenes')->assertOk();

    $listado
        ->assertJsonPath('data.0.estado', 'pagada')
        ->assertJsonPath('data.0.comprador', 'Ana')
        ->assertJsonPath('data.0.pagos.0.estado', 'aprobado')
        ->assertJsonPath('data.0.pagos.0.proveedor', 'manual');

    $pagoUlid = $listado->json('data.0.pagos.0.id');

    $this->postJson("/api/v1/pagos/{$pagoUlid}/reembolso")
        ->assertOk()
        ->assertJsonPath('data.estado', 'reembolsado')
        ->assertJsonPath('data.orden_estado', 'cancelada');
});

it('bloquea el reembolso si el derecho ya tuvo consumo', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    ['pago' => $pago, 'derecho' => $derecho] = cobrarOrdenNueva();

    $this->postJson("/api/v1/derechos/{$derecho}/consumos", ['unidades' => 1000])->assertCreated();

    $this->postJson("/api/v1/pagos/{$pago}/reembolso")
        ->assertStatus(422)
        ->assertJsonPath('code', 'REFUND_BLOCKED_USED');
});

it('bloquea el reembolso si hay una retención activa', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    ['pago' => $pago, 'derecho' => $derecho] = cobrarOrdenNueva();

    $this->postJson("/api/v1/derechos/{$derecho}/retenciones", ['unidades' => 1000])->assertCreated();

    $this->postJson("/api/v1/pagos/{$pago}/reembolso")
        ->assertStatus(422)
        ->assertJsonPath('code', 'REFUND_BLOCKED_USED');
});

it('el reembolso es idempotente', function (): void {
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    ['pago' => $pago] = cobrarOrdenNueva();

    $this->postJson("/api/v1/pagos/{$pago}/reembolso")->assertOk();
    $this->postJson("/api/v1/pagos/{$pago}/reembolso")
        ->assertOk()
        ->assertJsonPath('data.estado', 'reembolsado');
});

it('exige el permiso pagos.reembolsar', function (): void {
    ['owner' => $owner, 'tenant' => $tenant] = tenantConDueno();
    $gerente = User::factory()->create();
    vincularUsuario($tenant, $gerente, ['gerente-sucursal']); // tiene pagos.crear, no reembolsar

    Sanctum::actingAs($owner);
    ['pago' => $pago] = cobrarOrdenNueva();

    Sanctum::actingAs($gerente);
    $this->postJson("/api/v1/pagos/{$pago}/reembolso")
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('el webhook confirma un pago pendiente una sola vez (idempotente)', function (): void {
    $tenant = crearTenant('Pole House');
    app(TenantContext::class)->set($tenant);

    $producto = app(CrearProducto::class)->ejecutar('Pack', TipoProducto::Paquete, 89900, 'MXN', false, 8000);
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    $orden = app(CrearOrden::class)->ejecutar($persona, [['producto' => $producto, 'cantidad' => 1]]);

    $pago = Pago::create([
        'orden_id' => $orden->id,
        'proveedor' => 'simulada',
        'estado' => 'pendiente',
        'monto_minor' => $orden->total_minor,
        'moneda' => 'MXN',
        'referencia_externa' => 'wh-ref-1',
    ]);
    app(TenantContext::class)->clear();

    // Dos webhooks idénticos: el segundo no debe volver a cumplir la orden.
    $this->postJson('/api/v1/webhooks/pagos/simulada', ['referencia' => 'wh-ref-1', 'estado' => 'aprobado'])->assertOk();
    $this->postJson('/api/v1/webhooks/pagos/simulada', ['referencia' => 'wh-ref-1', 'estado' => 'aprobado'])->assertOk();

    app(TenantContext::class)->set($tenant);
    expect($pago->refresh()->estado->value)->toBe('aprobado');
    expect($orden->refresh()->estado->value)->toBe('pagada');
    expect(Acuerdo::where('persona_id', $persona->id)->count())->toBe(1);
    app(TenantContext::class)->clear();
});
