<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * Crea comprador + pack + orden pendiente; devuelve [orden, comprador].
 *
 * @param  array{slug: string, bearer: string}  $e
 * @return array{orden: string, comprador: string}
 */
function ordenPendiente(array $e): array
{
    $comprador = crearMiembroTenant($e, 'Ana');
    $pack = crearPackTenant($e, 8000);
    $orden = (string) test()->postJson("/api/v1/app/{$e['slug']}/ordenes", [
        'comprador_id' => $comprador,
        'items' => [['producto_id' => $pack, 'cantidad' => 1]],
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');

    return ['orden' => $orden, 'comprador' => $comprador];
}

/**
 * @param  array{slug: string, bearer: string}  $e
 */
function saldoComprador(array $e, string $comprador): int
{
    $d = test()->getJson("/api/v1/app/{$e['slug']}/miembros/{$comprador}/derechos", conBearer($e['bearer']))
        ->assertOk()->json('data.0.saldo');

    return (int) ($d ?? 0);
}

it('cobro manual aprueba y hace fulfillment de inmediato', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $o = ordenPendiente($e);

    test()->postJson("/api/v1/app/{$e['slug']}/ordenes/{$o['orden']}/cobrar", [
        'proveedor' => 'manual', 'metodo' => 'efectivo',
    ], conBearer($e['bearer']))
        ->assertCreated()
        ->assertJsonPath('data.estado', 'aprobado')
        ->assertJsonPath('data.orden.estado', 'pagada');

    expect(saldoComprador($e, $o['comprador']))->toBe(8000);
});

it('rechaza cobrar con una pasarela no activa (GATEWAY_UNAVAILABLE)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $o = ordenPendiente($e);

    test()->postJson("/api/v1/app/{$e['slug']}/ordenes/{$o['orden']}/cobrar", [
        'proveedor' => 'stripe',
    ], conBearer($e['bearer']))
        ->assertStatus(409)->assertJsonPath('code', 'GATEWAY_UNAVAILABLE');
});

it('cobro Stripe con llaves crea intento pendiente y el webhook confirma -> fulfillment', function (): void {
    Http::fake([
        'api.stripe.com/*' => Http::response([
            'id' => 'pi_prueba_123',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_prueba_123_secret_abc',
        ], 200),
    ]);

    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    // Activa Stripe con secret_key (sin webhook_secret: el webhook procesa sin firma).
    test()->putJson("/api/v1/app/{$e['slug']}/pasarelas/stripe", [
        'activa' => true, 'modo' => 'test', 'credenciales' => ['secret_key' => 'sk_test_x'],
    ], conBearer($e['bearer']))->assertOk();

    $o = ordenPendiente($e);

    // Cobro en linea -> pendiente + checkout con client_secret; la orden sigue pendiente.
    test()->postJson("/api/v1/app/{$e['slug']}/ordenes/{$o['orden']}/cobrar", [
        'proveedor' => 'stripe', 'metodo' => 'tarjeta',
    ], conBearer($e['bearer']))
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente')
        ->assertJsonPath('data.checkout.tipo', 'client_secret')
        ->assertJsonPath('data.checkout.client_secret', 'pi_prueba_123_secret_abc')
        ->assertJsonPath('data.orden.estado', 'pendiente');

    expect(saldoComprador($e, $o['comprador']))->toBe(0); // aun sin fulfillment

    // Webhook de Stripe (sin webhook_secret -> sin firma) confirma el intento.
    test()->postJson("/api/v1/webhooks/tenant/{$e['slug']}/stripe", [
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_prueba_123']],
    ])->assertOk();

    expect(saldoComprador($e, $o['comprador']))->toBe(8000); // fulfillment tras confirmar
});

it('el webhook de Stripe con webhook_secret rechaza firma invalida (400)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    test()->putJson("/api/v1/app/{$e['slug']}/pasarelas/stripe", [
        'activa' => true, 'modo' => 'test',
        'credenciales' => ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x'],
    ], conBearer($e['bearer']))->assertOk();

    test()->postJson("/api/v1/webhooks/tenant/{$e['slug']}/stripe", [
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_x']],
    ], ['Stripe-Signature' => 't=1,v1=firma-mala'])->assertStatus(400);
});

it('el cobro es idempotente por idempotency_key', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $o = ordenPendiente($e);

    $primero = (string) test()->postJson("/api/v1/app/{$e['slug']}/ordenes/{$o['orden']}/cobrar", [
        'proveedor' => 'manual', 'idempotency_key' => 'k-1',
    ], conBearer($e['bearer']))->assertCreated()->json('data.pago');

    $segundo = (string) test()->postJson("/api/v1/app/{$e['slug']}/ordenes/{$o['orden']}/cobrar", [
        'proveedor' => 'manual', 'idempotency_key' => 'k-1',
    ], conBearer($e['bearer']))->assertCreated()->json('data.pago');

    expect($segundo)->toBe($primero);
    // Fulfillment una sola vez.
    expect(saldoComprador($e, $o['comprador']))->toBe(8000);
});
