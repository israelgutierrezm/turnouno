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

it('un estudio nuevo entra en cobro por activos; el admin lo cambia a fijo y el cargo lo refleja', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    // Por defecto: cobro por alumnos activos.
    test()->getJson('/api/v1/plataforma/estudios', conTokenPlataforma())
        ->assertOk()->assertJsonPath('data.0.modo_cobro', 'activos');

    // El admin de plataforma lo cambia a cuota fija mensual.
    test()->putJson('/api/v1/plataforma/estudios/estudio-a', [
        'modo_cobro' => 'fijo', 'precio_por_alumno_minor' => 0, 'cuota_fija_minor' => 149900,
    ], conTokenPlataforma())->assertOk()->assertJsonPath('data.modo_cobro', 'fijo');

    // El cargo que ve el dueño ahora es la cuota fija (sin importar los alumnos activos).
    test()->getJson("/api/v1/app/{$e['slug']}/facturacion", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.modo_cobro', 'fijo')
        ->assertJsonPath('data.cuota_fija_minor', 149900)
        ->assertJsonPath('data.uso.cargo_estimado_minor', 149900);
});

it('el cambio de facturación de un estudio exige token de plataforma', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');
    estudioConSesion('estudio-a', 'a@correo.mx');

    test()->putJson('/api/v1/plataforma/estudios/estudio-a', [
        'modo_cobro' => 'fijo', 'precio_por_alumno_minor' => 0, 'cuota_fija_minor' => 1000,
    ], conTokenPlataforma('otro'))->assertUnauthorized();
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
