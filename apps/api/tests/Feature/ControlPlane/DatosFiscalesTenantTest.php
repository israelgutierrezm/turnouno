<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

it('cada tenant carga y consulta sus propios datos fiscales', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    // Al inicio no hay datos.
    test()->getJson("/api/v1/app/{$e['slug']}/datos-fiscales", conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data', null);

    $r = test()->putJson("/api/v1/app/{$e['slug']}/datos-fiscales", [
        'razon_social' => 'Estudio Demo SA de CV',
        'rfc' => 'abc010101ab9',
        'regimen_fiscal' => '601',
        'codigo_postal' => '06700',
    ], conBearer($e['bearer']))->assertOk();

    $r->assertJsonPath('data.razon_social', 'Estudio Demo SA de CV')
        ->assertJsonPath('data.rfc', 'ABC010101AB9') // normaliza a mayúsculas
        ->assertJsonPath('data.regimen_fiscal', '601')
        ->assertJsonPath('data.codigo_postal', '06700')
        ->assertJsonPath('data.facturapi_conectado', false);

    // La llave FacturAPI nunca se expone.
    expect($r->json('data'))->not->toHaveKey('facturapi_llave');

    test()->getJson("/api/v1/app/{$e['slug']}/datos-fiscales", conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.rfc', 'ABC010101AB9');
});

it('valida RFC, regimen y codigo postal', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $base = [
        'razon_social' => 'X', 'rfc' => 'ABC010101AB9', 'regimen_fiscal' => '601', 'codigo_postal' => '06700',
    ];

    test()->putJson("/api/v1/app/{$e['slug']}/datos-fiscales", ['rfc' => 'MALO'] + $base, conBearer($e['bearer']))
        ->assertStatus(422);
    test()->putJson("/api/v1/app/{$e['slug']}/datos-fiscales", ['codigo_postal' => '123'] + $base, conBearer($e['bearer']))
        ->assertStatus(422);
    test()->putJson("/api/v1/app/{$e['slug']}/datos-fiscales", ['regimen_fiscal' => 'abc'] + $base, conBearer($e['bearer']))
        ->assertStatus(422);
});

it('los datos fiscales son tenant-local (un estudio no ve los de otro)', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');

    test()->putJson("/api/v1/app/{$a['slug']}/datos-fiscales", [
        'razon_social' => 'A SA', 'rfc' => 'ABC010101AB9', 'regimen_fiscal' => '601', 'codigo_postal' => '06700',
    ], conBearer($a['bearer']))->assertOk();

    test()->getJson("/api/v1/app/{$b['slug']}/datos-fiscales", conBearer($b['bearer']))
        ->assertOk()->assertJsonPath('data', null);
});

it('cargar datos fiscales exige permiso de gestion del estudio', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    test()->getJson("/api/v1/app/{$e['slug']}/datos-fiscales", conBearer($coach))->assertForbidden();
});
