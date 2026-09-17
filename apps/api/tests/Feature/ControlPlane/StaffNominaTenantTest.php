<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\File;

/*
| Staff multi + sustitucion + nomina (R17): varios miembros del staff por sesion (con
| rol y sustitucion), esquema de pago por staff y calculo de nomina de un periodo
| (por clase / por asistente / por hora). Ver el roadmap.
*/

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * Crea un instructor y devuelve su ulid (via /instructores).
 *
 * @param  array{slug: string, bearer: string}  $e
 */
function instructorConUlid(array $e, string $email, string $nombre): string
{
    personalConSesion($e['slug'], $e['bearer'], $email, 'instructor');
    $lista = test()->getJson("/api/v1/app/{$e['slug']}/instructores", conBearer($e['bearer']))->assertOk()->json('data');

    return (string) collect($lista)->firstWhere('nombre', 'Personal')['id'];
}

it('asigna varios miembros del staff a una sesion con rol y sustitucion', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $sesion = crearSesionTenant($e, $semilla);

    // Dos instructores (comparten nombre 'Personal'; tomamos ambos ids del listado).
    personalConSesion($e['slug'], $e['bearer'], 'c1@correo.mx', 'instructor');
    personalConSesion($e['slug'], $e['bearer'], 'c2@correo.mx', 'instructor');
    $ids = collect($this->getJson("/api/v1/app/{$e['slug']}/instructores", conBearer($e['bearer']))->json('data'))->pluck('id');
    [$c1, $c2] = [$ids[0], $ids[1]];

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/staff", ['usuario_id' => $c1, 'rol' => 'instructor'], conBearer($e['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/staff", ['usuario_id' => $c2, 'rol' => 'sustituto', 'sustituye_a' => $c1], conBearer($e['bearer']))->assertCreated();

    $staff = $this->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/staff", conBearer($e['bearer']))->assertOk()->json('data');
    expect($staff)->toHaveCount(2);
    expect(collect($staff)->pluck('rol')->sort()->values()->all())->toBe(['instructor', 'sustituto']);
});

it('calcula la nomina por clase (monto por sesion impartida)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $coach = instructorConUlid($e, 'coach@correo.mx', 'Coach');

    // Esquema: 200.00 por clase.
    $this->putJson("/api/v1/app/{$e['slug']}/staff/{$coach}/esquema-pago", ['tipo' => 'por_clase', 'monto_minor' => 20000, 'moneda' => 'MXN'], conBearer($e['bearer']))->assertCreated();

    $s1 = crearSesionTenant($e, $semilla, cuando: '2026-10-01 08:00:00');
    $s2 = crearSesionTenant($e, $semilla, cuando: '2026-10-02 08:00:00');
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$s1}/staff", ['usuario_id' => $coach, 'rol' => 'instructor'], conBearer($e['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$s2}/staff", ['usuario_id' => $coach, 'rol' => 'instructor'], conBearer($e['bearer']))->assertCreated();

    $nomina = $this->getJson("/api/v1/app/{$e['slug']}/nomina?desde=2026-09-01&hasta=2026-12-31", conBearer($e['bearer']))->assertOk()->json('data');

    expect($nomina)->toHaveCount(1);
    expect($nomina[0]['tipo'])->toBe('por_clase');
    expect($nomina[0]['unidades'])->toEqual(2); // JSON colapsa 2.0 -> 2
    expect($nomina[0]['monto_total_minor'])->toBe(40000); // 2 clases x 200.00
});

it('calcula la nomina por asistente (monto por presente)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $coach = instructorConUlid($e, 'coach@correo.mx', 'Coach');
    $this->putJson("/api/v1/app/{$e['slug']}/staff/{$coach}/esquema-pago", ['tipo' => 'por_asistente', 'monto_minor' => 5000, 'moneda' => 'MXN'], conBearer($e['bearer']))->assertCreated();

    $sesion = crearSesionTenant($e, $semilla, capacidad: 5);
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/staff", ['usuario_id' => $coach, 'rol' => 'instructor'], conBearer($e['bearer']))->assertCreated();

    // Un miembro reserva y asiste (presente).
    $vp = venderPackAMiembroTenant($e, 8000);
    $reserva = (string) $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $vp['persona']], conBearer($e['bearer']))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/app/{$e['slug']}/reservas/{$reserva}/asistencia", ['estado' => 'presente'], conBearer($e['bearer']))->assertCreated();

    $nomina = $this->getJson("/api/v1/app/{$e['slug']}/nomina?desde=2026-09-01&hasta=2026-12-31", conBearer($e['bearer']))->assertOk()->json('data');
    expect($nomina[0]['monto_total_minor'])->toBe(5000); // 1 presente x 50.00
});

it('gestionar esquemas de pago exige estudio.gestionar', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = instructorConUlid($e, 'coach@correo.mx', 'Coach');
    $recep = personalConSesion($e['slug'], $e['bearer'], 'recep@correo.mx', 'recepcionista');

    $this->putJson("/api/v1/app/{$e['slug']}/staff/{$coach}/esquema-pago", ['tipo' => 'por_clase', 'monto_minor' => 10000, 'moneda' => 'MXN'], conBearer($recep))
        ->assertStatus(403);
});
