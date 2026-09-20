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
 * Crea un prospecto via API y devuelve su ulid.
 *
 * @param  array{slug: string, bearer: string}  $e
 * @param  array<string, mixed>  $extra
 */
function crearProspecto(array $e, string $nombre = 'Laura Prospecto', array $extra = []): string
{
    return (string) test()->postJson(
        "/api/v1/app/{$e['slug']}/crm/prospectos",
        array_merge(['nombre' => $nombre, 'origen' => 'web'], $extra),
        conBearer($e['bearer']),
    )->assertCreated()->json('data.id');
}

it('crea un prospecto, lo lista y cuenta el embudo por etapa', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    crearProspecto($e, 'Laura Prospecto', ['email' => 'laura@correo.mx', 'interes' => 'Pole']);

    $resp = $this->getJson("/api/v1/app/{$e['slug']}/crm/prospectos", conBearer($e['bearer']))->assertOk();
    $resp->assertJsonPath('data.0.nombre', 'Laura Prospecto')
        ->assertJsonPath('data.0.etapa', 'nuevo')
        ->assertJsonPath('data.0.origen', 'web')
        ->assertJsonPath('pipeline.nuevo', 1)
        ->assertJsonPath('pipeline.ganado', 0);
});

it('cambia de etapa y lo registra en la bitacora', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $p = crearProspecto($e);

    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}/etapa", ['etapa' => 'contactado'], conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.etapa', 'contactado');

    // La bitacora tiene el alta + el cambio de etapa (mas reciente primero).
    $actividades = $this->getJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}", conBearer($e['bearer']))
        ->assertOk()->json('data.actividades');
    expect(collect($actividades)->pluck('tipo')->all())->toContain('cambio_etapa');
    expect($actividades[0]['detalle'])->toContain('contactado');
});

it('registra una interaccion (nota) en el prospecto', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $p = crearProspecto($e);

    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}/actividades", [
        'tipo' => 'llamada', 'detalle' => 'Le llame, agenda clase muestra',
    ], conBearer($e['bearer']))->assertOk();

    $actividades = $this->getJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}", conBearer($e['bearer']))
        ->assertOk()->json('data.actividades');
    $llamada = collect($actividades)->firstWhere('tipo', 'llamada');
    expect($llamada['detalle'])->toBe('Le llame, agenda clase muestra');
});

it('convierte el prospecto en miembro, lo enlaza y es idempotente', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $p = crearProspecto($e, 'Laura Vega', ['email' => 'laura.vega@correo.mx']);

    $miembrosAntes = count($this->getJson("/api/v1/app/{$e['slug']}/miembros?tipo=miembro", conBearer($e['bearer']))->json('data'));

    $resp = $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}/convertir", [], conBearer($e['bearer']))
        ->assertCreated();
    $resp->assertJsonPath('data.etapa', 'ganado')
        ->assertJsonPath('data.miembro.nombre', 'Laura Vega')
        ->assertJsonPath('miembro.nombre', 'Laura Vega');

    // El miembro aparece en el directorio (creado una sola vez).
    $miembros = $this->getJson("/api/v1/app/{$e['slug']}/miembros?tipo=miembro", conBearer($e['bearer']))->json('data');
    expect(count($miembros))->toBe($miembrosAntes + 1);
    expect(collect($miembros)->pluck('nombre_completo')->all())->toContain('Laura Vega');

    // Convertir de nuevo no duplica: mismo miembro, sin crecer el directorio.
    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}/convertir", [], conBearer($e['bearer']))->assertCreated();
    $miembros2 = $this->getJson("/api/v1/app/{$e['slug']}/miembros?tipo=miembro", conBearer($e['bearer']))->json('data');
    expect(count($miembros2))->toBe($miembrosAntes + 1);
});

it('no permite convertir un prospecto perdido', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $p = crearProspecto($e);

    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}/etapa", [
        'etapa' => 'perdido', 'motivo' => 'Precio',
    ], conBearer($e['bearer']))->assertOk()->assertJsonPath('data.motivo', 'Precio');

    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos/{$p}/convertir", [], conBearer($e['bearer']))
        ->assertStatus(422);
});

it('un instructor no puede ver ni gestionar el CRM (RBAC: crm.*)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->getJson("/api/v1/app/{$e['slug']}/crm/prospectos", conBearer($coach))->assertStatus(403);
    $this->postJson("/api/v1/app/{$e['slug']}/crm/prospectos", ['nombre' => 'X'], conBearer($coach))->assertStatus(403);
});

it('el CRM es tenant-local: un estudio no resuelve el prospecto de otro', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');
    $p = crearProspecto($a, 'Solo de A');

    // El prospecto de A no existe en la BD de B: 404.
    $this->getJson("/api/v1/app/{$b['slug']}/crm/prospectos/{$p}", conBearer($b['bearer']))->assertNotFound();
    // Y el embudo de B no lo cuenta.
    $this->getJson("/api/v1/app/{$b['slug']}/crm/prospectos", conBearer($b['bearer']))
        ->assertOk()->assertJsonPath('pipeline.nuevo', 0);
});
