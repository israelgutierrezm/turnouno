<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Support\Facades\DB;
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
 * Activa Wellhub con una api_key en el estudio.
 *
 * @param  array{slug: string, bearer: string}  $e
 */
function activarWellhub(array $e): void
{
    test()->putJson("/api/v1/app/{$e['slug']}/integraciones/wellhub", [
        'activa' => true,
        'credenciales' => ['api_key' => 'wh_secreto_estudio'],
    ], conBearer($e['bearer']))->assertOk();
}

/**
 * @param  array{slug: string, bearer: string}  $e
 */
function sesionDemo(array $e): string
{
    return crearSesionTenant($e, agendaSemilla($e));
}

it('el propietario configura Wellhub con llaves cifradas que nunca se devuelven', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    test()->getJson("/api/v1/app/{$e['slug']}/integraciones", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.0.proveedor', 'wellhub')
        ->assertJsonPath('data.0.activa', false);

    $r = test()->putJson("/api/v1/app/{$e['slug']}/integraciones/wellhub", [
        'activa' => true, 'credenciales' => ['api_key' => 'wh_secreto_estudio'],
    ], conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.activa', true)
        ->assertJsonPath('data.llaves_configuradas', ['api_key']);

    expect($r->json())->not->toContain('wh_secreto_estudio');

    // Cifrada en la BD del tenant.
    $estudio = Estudio::query()->where('slug', 'estudio-a')->firstOrFail();
    $crudo = app(GestorDeConexionTenant::class)->ejecutarEn(
        $estudio,
        fn (): string => (string) DB::connection('tenant')->table('integraciones')->where('proveedor', 'wellhub')->value('credenciales'),
    );
    expect($crudo)->not->toContain('wh_secreto_estudio');
});

it('un no-propietario no puede configurar integraciones (RBAC)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $admin = personalConSesion($e['slug'], $e['bearer'], 'admin@correo.mx', 'admin');

    test()->getJson("/api/v1/app/{$e['slug']}/integraciones", conBearer($admin))->assertStatus(403);
    test()->putJson("/api/v1/app/{$e['slug']}/integraciones/wellhub", ['activa' => true], conBearer($admin))->assertStatus(403);
});

it('registra un check-in de Wellhub validado contra el proveedor (sin consumir creditos)', function (): void {
    Http::fake([
        'api.wellhub.com/*' => Http::response([
            'valid' => true, 'id' => 'wh_checkin_123', 'user_name' => 'Juan Wellhub',
        ], 200),
    ]);

    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    activarWellhub($e);
    $sesion = sesionDemo($e);

    test()->postJson("/api/v1/app/{$e['slug']}/checkins", [
        'proveedor' => 'wellhub', 'sesion_id' => $sesion, 'codigo' => 'ABC123',
    ], conBearer($e['bearer']))
        ->assertCreated()
        ->assertJsonPath('data.proveedor', 'wellhub')
        ->assertJsonPath('data.usuario', 'Juan Wellhub')
        ->assertJsonPath('data.estado', 'validado');

    // Aparece en el roster de check-ins de la sesion.
    test()->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/checkins", conBearer($e['bearer']))
        ->assertOk()->assertJsonCount(1, 'data');
});

it('rechaza un codigo invalido (CHECKIN_INVALID)', function (): void {
    Http::fake(['api.wellhub.com/*' => Http::response(['valid' => false], 200)]);

    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    activarWellhub($e);
    $sesion = sesionDemo($e);

    test()->postJson("/api/v1/app/{$e['slug']}/checkins", [
        'proveedor' => 'wellhub', 'sesion_id' => $sesion, 'codigo' => 'MALO',
    ], conBearer($e['bearer']))
        ->assertStatus(422)->assertJsonPath('code', 'CHECKIN_INVALID');
});

it('rechaza check-in si la integracion no esta activa (INTEGRATION_UNAVAILABLE)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $sesion = sesionDemo($e);

    test()->postJson("/api/v1/app/{$e['slug']}/checkins", [
        'proveedor' => 'totalpass', 'sesion_id' => $sesion, 'codigo' => 'X',
    ], conBearer($e['bearer']))
        ->assertStatus(409)->assertJsonPath('code', 'INTEGRATION_UNAVAILABLE');
});

it('el check-in es idempotente por la referencia del proveedor', function (): void {
    Http::fake([
        'api.wellhub.com/*' => Http::response(['valid' => true, 'id' => 'wh_mismo', 'user_name' => 'Ana'], 200),
    ]);

    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    activarWellhub($e);
    $sesion = sesionDemo($e);

    $uno = (string) test()->postJson("/api/v1/app/{$e['slug']}/checkins", [
        'proveedor' => 'wellhub', 'sesion_id' => $sesion, 'codigo' => 'C1',
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');
    $dos = (string) test()->postJson("/api/v1/app/{$e['slug']}/checkins", [
        'proveedor' => 'wellhub', 'sesion_id' => $sesion, 'codigo' => 'C2',
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');

    expect($dos)->toBe($uno); // misma referencia del proveedor -> un solo check-in
    test()->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/checkins", conBearer($e['bearer']))
        ->assertOk()->assertJsonCount(1, 'data');
});
