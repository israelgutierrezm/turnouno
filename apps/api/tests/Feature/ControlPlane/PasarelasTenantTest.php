<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

it('el propietario lista las pasarelas configurables (inactivas por defecto)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $this->getJson("/api/v1/app/{$e['slug']}/pasarelas", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.0.activa', false)
        ->assertJsonPath('data.0.llaves_configuradas', []);
});

it('configura Stripe con llaves cifradas que NUNCA se devuelven', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $r = $this->putJson("/api/v1/app/{$e['slug']}/pasarelas/stripe", [
        'activa' => true,
        'modo' => 'live',
        'credenciales' => ['secret_key' => 'sk_live_secreto', 'webhook_secret' => 'whsec_x'],
    ], conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.activa', true)
        ->assertJsonPath('data.modo', 'live')
        ->assertJsonPath('data.llaves_configuradas', ['secret_key', 'webhook_secret']);

    // La respuesta no filtra los secretos.
    expect($r->json())->not->toContain('sk_live_secreto');

    // En la BD del tenant, la columna esta cifrada (no contiene el secreto en claro).
    $estudio = Estudio::query()->where('slug', 'estudio-a')->firstOrFail();
    $crudo = app(GestorDeConexionTenant::class)->ejecutarEn(
        $estudio,
        fn (): string => (string) DB::connection('tenant')->table('configuraciones_pasarela')->where('proveedor', 'stripe')->value('credenciales'),
    );
    expect($crudo)->not->toContain('sk_live_secreto');
    expect($crudo)->not->toBe('');
});

it('hace merge de llaves: una segunda actualizacion conserva las anteriores', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $this->putJson("/api/v1/app/{$e['slug']}/pasarelas/stripe", [
        'activa' => true, 'modo' => 'test', 'credenciales' => ['secret_key' => 'sk_1'],
    ], conBearer($e['bearer']))->assertOk();

    $this->putJson("/api/v1/app/{$e['slug']}/pasarelas/stripe", [
        'activa' => true, 'modo' => 'test', 'credenciales' => ['webhook_secret' => 'wh_1'],
    ], conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.llaves_configuradas', ['secret_key', 'webhook_secret']);
});

it('un no-propietario no puede configurar pasarelas (RBAC)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $admin = personalConSesion($e['slug'], $e['bearer'], 'admin@correo.mx', 'admin');

    $this->getJson("/api/v1/app/{$e['slug']}/pasarelas", conBearer($admin))->assertStatus(403);
    $this->putJson("/api/v1/app/{$e['slug']}/pasarelas/stripe", [
        'activa' => true, 'modo' => 'test',
    ], conBearer($admin))->assertStatus(403);
});

it('las pasarelas son por estudio: la config de uno no aparece en otro', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');

    $this->putJson("/api/v1/app/{$a['slug']}/pasarelas/stripe", [
        'activa' => true, 'modo' => 'live', 'credenciales' => ['secret_key' => 'sk_a'],
    ], conBearer($a['bearer']))->assertOk();

    // En B, Stripe sigue inactivo y sin llaves.
    $stripeB = collect($this->getJson("/api/v1/app/{$b['slug']}/pasarelas", conBearer($b['bearer']))->assertOk()->json('data'))
        ->firstWhere('proveedor', 'stripe');
    expect($stripeB['activa'])->toBeFalse();
    expect($stripeB['llaves_configuradas'])->toBe([]);
});
