<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

function configurarMercadoPago(): Tenant
{
    ['tenant' => $tenant, 'owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    test()->putJson('/api/v1/pasarelas/mercadopago', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => ['access_token' => 'APP_USR_x', 'webhook_secret' => 'mpsecret'],
    ])->assertOk();

    return $tenant;
}

/**
 * @return array{persona: string, pago: string, referencia: string}
 */
function cobroMercadoPagoPendiente(): array
{
    Http::fake([
        'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref_1', 'init_point' => 'http://mp/pay'], 200),
    ]);

    $producto = crearProductoPack();
    $persona = test()->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = test()->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $respuesta = test()->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'mercadopago'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente');

    return [
        'persona' => $persona,
        'pago' => $respuesta->json('data.id'),
        'referencia' => $respuesta->json('data.referencia'),
        'url' => $respuesta->json('data.checkout.url'),
    ];
}

function webhookMercadoPago(Tenant $tenant, string $dataId, string $secreto, ?string $firma = null): TestResponse
{
    $requestId = 'req-1';
    $ts = (string) time();
    $manifiesto = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $v1 = $firma ?? hash_hmac('sha256', $manifiesto, $secreto);
    $cuerpo = (string) json_encode(['action' => 'payment.updated', 'data' => ['id' => $dataId]]);

    return test()->call('POST', "/api/v1/webhooks/mercadopago/{$tenant->ulid}", [], [], [], [
        'HTTP_X_REQUEST_ID' => $requestId,
        'HTTP_X_SIGNATURE' => "ts={$ts},v1={$v1}",
        'CONTENT_TYPE' => 'application/json',
    ], $cuerpo);
}

it('crea una preferencia real de Mercado Pago con el token del tenant', function (): void {
    configurarMercadoPago();

    ['pago' => $pagoUlid, 'referencia' => $referencia, 'url' => $url] = cobroMercadoPagoPendiente();

    expect($referencia)->toBe('pref_1');
    // El cobro devuelve el init_point para redirigir al cliente.
    expect($url)->toBe('http://mp/pay');

    Http::assertSent(function (Request $request) use ($pagoUlid): bool {
        return str_contains($request->url(), '/checkout/preferences')
            && $request->hasHeader('Authorization', 'Bearer APP_USR_x')
            && $request['external_reference'] === $pagoUlid;
    });
});

it('el webhook de Mercado Pago con firma valida confirma y concede el derecho', function (): void {
    $tenant = configurarMercadoPago();
    ['persona' => $persona, 'pago' => $pagoUlid] = cobroMercadoPagoPendiente();

    // El webhook consulta el pago en la API: devuelve approved + external_reference.
    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response(['status' => 'approved', 'external_reference' => $pagoUlid], 200),
    ]);

    webhookMercadoPago($tenant, 'mp_pay_1', 'mpsecret')->assertOk();

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('rechaza el webhook de Mercado Pago con firma invalida', function (): void {
    $tenant = configurarMercadoPago();
    ['persona' => $persona, 'pago' => $pagoUlid] = cobroMercadoPagoPendiente();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response(['status' => 'approved', 'external_reference' => $pagoUlid], 200),
    ]);

    webhookMercadoPago($tenant, 'mp_pay_1', 'mpsecret', firma: 'firmafalsa')->assertStatus(400);

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(0, 'data');
});
