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

/**
 * Código de referido de un miembro nuevo. Devuelve [personaUlid, codigo].
 *
 * @param  array{slug: string, bearer: string}  $e
 * @return array{0: string, 1: string}
 */
function miembroConCodigo(array $e, string $nombre = 'Refi'): array
{
    $persona = crearMiembroTenant($e, $nombre);
    $codigo = (string) test()->getJson("/api/v1/app/{$e['slug']}/miembros/{$persona}/codigo-referido", conBearer($e['bearer']))
        ->assertOk()->json('data.codigo');

    return [$persona, $codigo];
}

/**
 * @param  array{slug: string, bearer: string}  $e
 */
function prospectoConCodigo(array $e, string $codigo, string $nombre = 'Nuevo Referido'): string
{
    return (string) test()->postJson("/api/v1/app/{$e['slug']}/crm/prospectos", [
        'nombre' => $nombre, 'origen' => 'referido', 'codigo_referido' => $codigo,
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');
}

it('genera un codigo de referido por miembro (idempotente)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    [$persona, $codigo] = miembroConCodigo($e);

    expect($codigo)->not->toBe('');
    // Segunda llamada devuelve el MISMO codigo.
    $otra = (string) $this->getJson("/api/v1/app/{$e['slug']}/miembros/{$persona}/codigo-referido", conBearer($e['bearer']))
        ->assertOk()->json('data.codigo');
    expect($otra)->toBe($codigo);
});

it('atribuye el prospecto a quien refirio y queda pendiente', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    [, $codigo] = miembroConCodigo($e, 'Ana Referidora');
    prospectoConCodigo($e, $codigo);

    $resp = $this->getJson("/api/v1/app/{$e['slug']}/referidos", conBearer($e['bearer']))->assertOk();
    $resp->assertJsonPath('resumen.pendientes', 1)
        ->assertJsonPath('data.0.referidor', 'Ana Referidora')
        ->assertJsonPath('data.0.estado', 'pendiente');
    expect($resp->json('data.0.recompensa'))->toBeNull();
});

it('al convertir el referido genera un cupon usable para quien refirio', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    [, $codigo] = miembroConCodigo($e);
    $prospecto = prospectoConCodigo($e, $codigo);

    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$prospecto}/convertir", [], conBearer($e['bearer']))->assertCreated();

    $referido = $this->getJson("/api/v1/app/{$e['slug']}/referidos", conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('resumen.convertidos', 1)->json('data.0');
    expect($referido['estado'])->toBe('convertido');
    $cupon = $referido['recompensa'];
    expect($cupon)->toStartWith('REF-');

    // El cupon (monto fijo 10000 por defecto) aplica en una orden.
    $comprador = crearMiembroTenant($e, 'Compradora');
    $producto = crearPackTenant($e, 8000); // 89900
    $this->postJson("/api/v1/app/{$e['slug']}/ordenes", [
        'comprador_id' => $comprador,
        'items' => [['producto_id' => $producto, 'cantidad' => 1]],
        'codigo_promo' => $cupon,
    ], conBearer($e['bearer']))->assertCreated()
        ->assertJsonPath('data.descuento_minor', 10000)
        ->assertJsonPath('data.total_minor', 79900);
});

it('con el programa inactivo no genera cupon al convertir', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $this->putJson("/api/v1/app/{$e['slug']}/referidos/programa", [
        'recompensa_tipo' => 'monto_fijo', 'recompensa_valor' => 5000, 'vigencia_dias' => 30, 'activo' => false,
    ], conBearer($e['bearer']))->assertOk()->assertJsonPath('data.activo', false);

    [, $codigo] = miembroConCodigo($e);
    $prospecto = prospectoConCodigo($e, $codigo);
    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$prospecto}/convertir", [], conBearer($e['bearer']))->assertCreated();

    $referido = $this->getJson("/api/v1/app/{$e['slug']}/referidos", conBearer($e['bearer']))->assertOk()->json('data.0');
    expect($referido['estado'])->toBe('convertido');
    expect($referido['recompensa'])->toBeNull();
});

it('el programa tiene default y se puede configurar', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $this->getJson("/api/v1/app/{$e['slug']}/referidos/programa", conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.recompensa_valor', 10000)->assertJsonPath('data.activo', true);

    $this->putJson("/api/v1/app/{$e['slug']}/referidos/programa", [
        'recompensa_tipo' => 'porcentaje', 'recompensa_valor' => 1000, 'vigencia_dias' => 60,
    ], conBearer($e['bearer']))->assertOk()
        ->assertJsonPath('data.recompensa_tipo', 'porcentaje')
        ->assertJsonPath('data.recompensa_valor', 1000);
});

it('RBAC: el instructor no ve referidos', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->getJson("/api/v1/app/{$e['slug']}/referidos", conBearer($coach))->assertStatus(403);
});

it('los codigos de referido son tenant-local: el codigo de A no atribuye en B', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');
    [, $codigo] = miembroConCodigo($a);

    // En B ese codigo no existe: el prospecto se crea pero sin referido atribuido.
    prospectoConCodigo($b, $codigo);
    $this->getJson("/api/v1/app/{$b['slug']}/referidos", conBearer($b['bearer']))
        ->assertOk()->assertJsonPath('resumen.pendientes', 0);
});
