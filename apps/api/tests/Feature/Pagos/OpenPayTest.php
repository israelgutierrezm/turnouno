<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Configura OpenPay activa con llaves + credenciales de webhook. Devuelve nada;
 * deja al owner autenticado.
 */
function openpayConfigurado(): void
{
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    test()->putJson('/api/v1/pasarelas/openpay', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => [
            'merchant_id' => 'm_test',
            'private_key' => 'sk_openpay_test',
            'webhook_user' => 'hook',
            'webhook_password' => 'secreto',
        ],
    ])->assertOk();
}

/**
 * @return array{persona: string, referencia: string}
 */
function cobroOpenPayPendiente(string $cargoId): array
{
    Http::fake([
        '*openpay.mx/*' => Http::response(['id' => $cargoId, 'status' => 'in_progress'], 200),
    ]);

    $producto = crearProductoPack();
    $persona = test()->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = test()->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $referencia = test()->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'openpay', 'metodo' => 'oxxo'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente')
        ->json('data.referencia');

    return ['persona' => $persona, 'referencia' => $referencia];
}

function basicAuth(string $usuario, string $password): string
{
    return 'Basic '.base64_encode($usuario.':'.$password);
}

it('crea un cargo real de OpenPay con la private key del tenant', function (): void {
    openpayConfigurado();

    ['referencia' => $referencia] = cobroOpenPayPendiente('tr_openpay_1');

    expect($referencia)->toBe('tr_openpay_1');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'openpay.mx')
            && str_contains($request->url(), '/charges')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_openpay_test:'))
            && (float) $request['amount'] === 899.0
            && $request['method'] === 'store';
    });
});

it('el webhook de OpenPay con credenciales validas confirma y concede el derecho', function (): void {
    openpayConfigurado();
    ['persona' => $persona, 'referencia' => $referencia] = cobroOpenPayPendiente('tr_ok');

    $cuerpo = (string) json_encode([
        'type' => 'charge.succeeded',
        'transaction' => ['id' => $referencia, 'status' => 'completed'],
    ]);

    $this->call('POST', '/api/v1/webhooks/openpay', [], [], [], [
        'HTTP_AUTHORIZATION' => basicAuth('hook', 'secreto'),
        'CONTENT_TYPE' => 'application/json',
    ], $cuerpo)->assertOk();

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('rechaza el webhook de OpenPay con credenciales invalidas', function (): void {
    openpayConfigurado();
    ['persona' => $persona, 'referencia' => $referencia] = cobroOpenPayPendiente('tr_bad');

    $cuerpo = (string) json_encode([
        'type' => 'charge.succeeded',
        'transaction' => ['id' => $referencia, 'status' => 'completed'],
    ]);

    $this->call('POST', '/api/v1/webhooks/openpay', [], [], [], [
        'HTTP_AUTHORIZATION' => basicAuth('hook', 'incorrecto'),
        'CONTENT_TYPE' => 'application/json',
    ], $cuerpo)->assertStatus(400);

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(0, 'data');
});
