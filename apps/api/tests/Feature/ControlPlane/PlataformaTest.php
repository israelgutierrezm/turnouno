<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * @return array<string, string>
 */
function conTokenPlataforma(string $token = 'token-plataforma'): array
{
    return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
}

it('sin token de plataforma configurado el apartado esta deshabilitado (401)', function (): void {
    // No se configura turnouno.plataforma.token.
    test()->getJson('/api/v1/plataforma/estudios', conTokenPlataforma())->assertUnauthorized();
});

it('rechaza un token de plataforma incorrecto (401)', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');

    test()->getJson('/api/v1/plataforma/estudios', conTokenPlataforma('otro'))->assertUnauthorized();
});

it('con el token correcto lista todos los estudios (control plane)', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');
    estudioConSesion('estudio-a', 'a@correo.mx');
    estudioConSesion('estudio-b', 'b@correo.mx');

    test()->getJson('/api/v1/plataforma/estudios', conTokenPlataforma())
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonCount(2, 'data');
});

it('carga la llave de la cuenta FacturAPI sin devolverla nunca', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');

    test()->getJson('/api/v1/plataforma/configuracion', conTokenPlataforma())
        ->assertOk()->assertJsonPath('data.facturapi_configurada', false);

    $r = test()->putJson('/api/v1/plataforma/configuracion', [
        'facturapi_llave' => 'sk_test_llave_de_plataforma',
    ], conTokenPlataforma())->assertOk();

    $r->assertJsonPath('data.facturapi_configurada', true);
    // Nunca se expone la llave en claro.
    expect(json_encode($r->json()))->not->toContain('sk_test_llave_de_plataforma');

    test()->getJson('/api/v1/plataforma/configuracion', conTokenPlataforma())
        ->assertOk()->assertJsonPath('data.facturapi_configurada', true);
});
