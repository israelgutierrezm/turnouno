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
 * Fija (upsert) una regla de capacidad por canal para la oferta de la semilla.
 *
 * @param  array{slug: string, bearer: string}  $e
 * @param  array{oferta: string, sucursal: string}  $semilla
 * @param  array<string, mixed>  $regla
 */
function fijarReglaCanal(array $e, array $semilla, array $regla): void
{
    test()->putJson("/api/v1/app/{$e['slug']}/ofertas/{$semilla['oferta']}/capacidad-canal", $regla, conBearer($e['bearer']))
        ->assertCreated();
}

it('etiqueta la reserva con su canal (directo por defecto)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 5);

    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $b = venderPackAMiembroTenant($e, 8000, 'Beto');

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona']], conBearer($e['bearer']))
        ->assertCreated()->assertJsonPath('data.canal', 'directo');
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona'], 'canal' => 'wellhub'], conBearer($e['bearer']))
        ->assertCreated()->assertJsonPath('data.canal', 'wellhub');
});

it('una regla de canal aparta cupos: el canal directo no puede tomar los reservados', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    fijarReglaCanal($e, $semilla, ['canal' => 'wellhub', 'cupos' => 2, 'liberar_horas_antes' => 0]);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 3);

    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $b = venderPackAMiembroTenant($e, 8000, 'Beto');

    // Directo solo puede tomar 1 (3 - 2 reservados para wellhub).
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona']], conBearer($e['bearer']))
        ->assertCreated();
    // Segundo directo: lleno para directo, aunque queden cupos de wellhub.
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona']], conBearer($e['bearer']))
        ->assertStatus(409)->assertJsonPath('code', 'CAPACITY_FULL');
});

it('el canal con reserva usa sus cupos apartados y no se sobrevende', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    fijarReglaCanal($e, $semilla, ['canal' => 'wellhub', 'cupos' => 2, 'liberar_horas_antes' => 0]);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 3);

    $a = venderPackAMiembroTenant($e, 8000, 'Ana');   // directo
    $b = venderPackAMiembroTenant($e, 8000, 'Beto');  // wellhub
    $c = venderPackAMiembroTenant($e, 8000, 'Ceci');  // wellhub
    $d = venderPackAMiembroTenant($e, 8000, 'Dani');  // wellhub extra

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona']], conBearer($e['bearer']))->assertCreated();
    // wellhub toma sus 2 cupos apartados.
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona'], 'canal' => 'wellhub'], conBearer($e['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $c['persona'], 'canal' => 'wellhub'], conBearer($e['bearer']))->assertCreated();
    // Un 4to (por cualquier canal) ya no cabe: 3/3 sin sobreventa.
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $d['persona'], 'canal' => 'wellhub'], conBearer($e['bearer']))
        ->assertStatus(409)->assertJsonPath('code', 'CAPACITY_FULL');

    $roster = $this->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", conBearer($e['bearer']))->assertOk()->json('data');
    expect(collect($roster)->where('estado', 'confirmada')->count())->toBe(3);
});

it('liberacion progresiva: cupos reservados vuelven al pool general al acercarse el inicio', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    // liberar_horas_antes enorme => la ventana de liberacion ya paso => cupos liberados.
    fijarReglaCanal($e, $semilla, ['canal' => 'wellhub', 'cupos' => 3, 'liberar_horas_antes' => 8760]);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 3);

    // Directo puede tomar los 3 porque los cupos de wellhub ya se liberaron.
    foreach (['Ana', 'Beto', 'Ceci'] as $nombre) {
        $p = venderPackAMiembroTenant($e, 8000, $nombre);
        $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $p['persona']], conBearer($e['bearer']))->assertCreated();
    }
});

it('CRUD de reglas de capacidad por canal (upsert por canal)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);

    fijarReglaCanal($e, $semilla, ['canal' => 'wellhub', 'cupos' => 2]);
    // Upsert: mismo canal actualiza, no duplica.
    fijarReglaCanal($e, $semilla, ['canal' => 'wellhub', 'cupos' => 5]);
    fijarReglaCanal($e, $semilla, ['canal' => 'classpass', 'cupos' => 1]);

    $reglas = $this->getJson("/api/v1/app/{$e['slug']}/ofertas/{$semilla['oferta']}/capacidad-canal", conBearer($e['bearer']))
        ->assertOk()->json('data');
    expect($reglas)->toHaveCount(2);
    $wellhub = collect($reglas)->firstWhere('canal', 'wellhub');
    expect($wellhub['cupos'])->toBe(5);

    $this->deleteJson("/api/v1/app/{$e['slug']}/capacidad-canal/{$wellhub['id']}", [], conBearer($e['bearer']))->assertOk();
    $reglas = $this->getJson("/api/v1/app/{$e['slug']}/ofertas/{$semilla['oferta']}/capacidad-canal", conBearer($e['bearer']))->assertOk()->json('data');
    expect($reglas)->toHaveCount(1);
});

it('RBAC: el instructor no puede definir reglas de capacidad por canal', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->putJson("/api/v1/app/{$e['slug']}/ofertas/{$semilla['oferta']}/capacidad-canal", [
        'canal' => 'wellhub', 'cupos' => 2,
    ], conBearer($coach))->assertStatus(403);
});
