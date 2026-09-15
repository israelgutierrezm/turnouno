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
 * Prepara oferta + sucursal (zona America/Mexico_City) en la BD del estudio y
 * devuelve sus ulids.
 *
 * @param  array{slug: string, bearer: string}  $e
 * @return array{oferta: string, sucursal: string}
 */
function agendaSemilla(array $e): array
{
    $programa = (string) test()->postJson("/api/v1/app/{$e['slug']}/programas", ['nombre' => 'Pole'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $actividad = (string) test()->postJson("/api/v1/app/{$e['slug']}/programas/{$programa}/actividades", ['nombre' => 'Pole Sport'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $oferta = (string) test()->postJson("/api/v1/app/{$e['slug']}/actividades/{$actividad}/ofertas", [
        'nombre' => 'Nivel 1', 'modalidad' => 'grupal', 'capacidad' => 12,
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');

    $org = (string) test()->postJson("/api/v1/app/{$e['slug']}/organizaciones", ['nombre' => 'Org'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $sucursal = (string) test()->postJson("/api/v1/app/{$e['slug']}/organizaciones/{$org}/sucursales", [
        'nombre' => 'Roma Norte', 'zona_horaria' => 'America/Mexico_City',
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');

    return ['oferta' => $oferta, 'sucursal' => $sucursal];
}

it('crea una sesion convirtiendo la hora local de la sucursal a UTC (con snapshot de zona)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);

    // Mexico City opera en UTC-6 todo el año (sin horario de verano): 08:00 local => 14:00 UTC.
    $respuesta = $this->postJson("/api/v1/app/{$e['slug']}/sesiones", [
        'oferta_id' => $semilla['oferta'],
        'sucursal_id' => $semilla['sucursal'],
        'inicia_en_local' => '2026-10-01 08:00:00',
        'duracion_minutos' => 60,
    ], conBearer($e['bearer']));

    $respuesta->assertCreated()
        ->assertJsonPath('data.oferta', 'Nivel 1')
        ->assertJsonPath('data.zona_horaria', 'America/Mexico_City')
        ->assertJsonPath('data.inicia_en', '2026-10-01T14:00:00+00:00')
        ->assertJsonPath('data.termina_en', '2026-10-01T15:00:00+00:00')
        ->assertJsonPath('data.capacidad', 12)
        ->assertJsonPath('data.estado', 'programada');
});

it('lista y cancela sesiones del estudio', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);

    $sesion = (string) $this->postJson("/api/v1/app/{$e['slug']}/sesiones", [
        'oferta_id' => $semilla['oferta'],
        'sucursal_id' => $semilla['sucursal'],
        'inicia_en_local' => '2026-10-01 08:00:00',
        'duracion_minutos' => 60,
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');

    $this->getJson("/api/v1/app/{$e['slug']}/sesiones", conBearer($e['bearer']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sesion);

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/cancelar", [], conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.estado', 'cancelada');
});

it('las sesiones son tenant-local: un estudio no ve las de otro', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');

    $semillaA = agendaSemilla($a);
    $this->postJson("/api/v1/app/{$a['slug']}/sesiones", [
        'oferta_id' => $semillaA['oferta'],
        'sucursal_id' => $semillaA['sucursal'],
        'inicia_en_local' => '2026-10-01 08:00:00',
        'duracion_minutos' => 60,
    ], conBearer($a['bearer']))->assertCreated();

    $this->getJson("/api/v1/app/{$a['slug']}/sesiones", conBearer($a['bearer']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/app/{$b['slug']}/sesiones", conBearer($b['bearer']))->assertOk()->assertJsonCount(0, 'data');
});

it('un instructor puede ver la agenda pero no gestionarla (RBAC tenant-local)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->getJson("/api/v1/app/{$e['slug']}/sesiones", conBearer($coach))->assertOk();

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones", [
        'oferta_id' => $semilla['oferta'],
        'sucursal_id' => $semilla['sucursal'],
        'inicia_en_local' => '2026-10-01 08:00:00',
        'duracion_minutos' => 60,
    ], conBearer($coach))->assertStatus(403);
});
