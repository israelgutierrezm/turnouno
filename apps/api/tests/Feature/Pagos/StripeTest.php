<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Configura Stripe activa con llaves y devuelve el owner autenticado.
 */
function stripeConfigurado(string $secret = 'sk_test_x', string $webhook = 'whsec_test'): User
{
    ['owner' => $owner] = tenantConDueno();
    Sanctum::actingAs($owner);

    test()->putJson('/api/v1/pasarelas/stripe', [
        'activa' => true,
        'modo' => 'test',
        'credenciales' => ['secret_key' => $secret, 'webhook_secret' => $webhook],
    ])->assertOk();

    return $owner;
}

/**
 * @return array{persona: string, referencia: string}
 */
function cobroStripePendiente(string $intentId): array
{
    Http::fake([
        'api.stripe.com/*' => Http::response(['id' => $intentId, 'status' => 'requires_payment_method'], 200),
    ]);

    $producto = crearProductoPack();
    $persona = test()->postJson('/api/v1/personas', ['nombre' => 'Ana'])->assertCreated()->json('data.id');
    $orden = test()->postJson('/api/v1/ordenes', [
        'persona_id' => $persona,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $referencia = test()->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'stripe', 'metodo' => 'tarjeta'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente')
        ->json('data.referencia');

    return ['persona' => $persona, 'referencia' => $referencia];
}

function firmaStripe(string $cuerpo, string $secreto): string
{
    $marca = (string) time();

    return 't='.$marca.',v1='.hash_hmac('sha256', $marca.'.'.$cuerpo, $secreto);
}

it('crea un PaymentIntent real de Stripe con la llave del tenant', function (): void {
    stripeConfigurado('sk_test_abc');

    ['referencia' => $referencia] = cobroStripePendiente('pi_test_123');

    expect($referencia)->toBe('pi_test_123');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'api.stripe.com/v1/payment_intents')
            && $request->hasHeader('Authorization', 'Bearer sk_test_abc')
            && (int) $request['amount'] === 89900;
    });
});

it('el webhook de Stripe con firma valida confirma y concede el derecho', function (): void {
    stripeConfigurado(webhook: 'whsec_ok');
    ['persona' => $persona, 'referencia' => $referencia] = cobroStripePendiente('pi_ok');

    $cuerpo = (string) json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $referencia]]]);
    $firma = firmaStripe($cuerpo, 'whsec_ok');

    $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $firma,
        'CONTENT_TYPE' => 'application/json',
    ], $cuerpo)->assertOk();

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('rechaza el webhook de Stripe con firma invalida', function (): void {
    stripeConfigurado(webhook: 'whsec_ok');
    ['persona' => $persona, 'referencia' => $referencia] = cobroStripePendiente('pi_bad');

    $cuerpo = (string) json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $referencia]]]);

    $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=123,v1=firmafalsa',
        'CONTENT_TYPE' => 'application/json',
    ], $cuerpo)->assertStatus(400);

    // No se concedió el derecho.
    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(0, 'data');
});
