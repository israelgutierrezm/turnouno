<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

it('smart-fill lista solo las clases proximas con lugares libres', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $ahora = Carbon::now('America/Mexico_City');

    // S1 (proxima, cupo 3, 1 confirmada) -> aparece con 2 libres.
    $s1 = crearSesionTenant($e, $semilla, capacidad: 3, cuando: $ahora->copy()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'));
    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$s1}/reservas", ['persona_id' => $a['persona']], conBearer($e['bearer']))->assertCreated();

    // S2 (proxima, cupo 1, 1 confirmada) -> llena, se excluye.
    $s2 = crearSesionTenant($e, $semilla, capacidad: 1, cuando: $ahora->copy()->addDays(4)->setTime(10, 0)->format('Y-m-d H:i:s'));
    $b = venderPackAMiembroTenant($e, 8000, 'Beto');
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$s2}/reservas", ['persona_id' => $b['persona']], conBearer($e['bearer']))->assertCreated();

    // S3 (pasada, cupo 3, vacia) -> se excluye por ser pasada.
    crearSesionTenant($e, $semilla, capacidad: 3, cuando: $ahora->copy()->subDays(3)->setTime(8, 0)->format('Y-m-d H:i:s'));

    $data = $this->getJson("/api/v1/app/{$e['slug']}/sesiones/oportunidades", conBearer($e['bearer']))
        ->assertOk()->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($s1);
    expect($data[0]['capacidad'])->toBe(3);
    expect($data[0]['ocupados'])->toBe(1);
    expect($data[0]['libres'])->toBe(2);
    expect($data[0]['en_espera'])->toBe(0);
    expect($data[0]['ocupacion_pct'])->toBe(33);
});

it('smart-fill promueve la lista de espera de una clase con lugares liberados', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $ahora = Carbon::now('America/Mexico_City');

    // Clase de cupo 1 (llena por Ana); Beto queda en espera con exactamente 1 credito.
    $sesion = crearSesionTenant($e, $semilla, capacidad: 1, cuando: $ahora->copy()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'));
    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $b = venderPackAMiembroTenant($e, 1000, 'Beto');

    $reservaA = (string) $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona']], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona'], 'esperar' => true], conBearer($e['bearer']))
        ->assertCreated()->assertJsonPath('data.estado', 'en_espera');

    // Beto se queda sin credito: al liberarse el cupo, la auto-promocion NO puede
    // ofrecerle (saldo insuficiente), asi que el lugar queda libre y Beto en espera.
    $this->postJson("/api/v1/app/{$e['slug']}/derechos/{$b['derecho']}/consumos", ['unidades' => 1000], conBearer($e['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$e['slug']}/reservas/{$reservaA}/cancelar", [], conBearer($e['bearer']))->assertOk();

    // La oportunidad muestra el cupo libre y a alguien esperando.
    $data = $this->getJson("/api/v1/app/{$e['slug']}/sesiones/oportunidades", conBearer($e['bearer']))->assertOk()->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['libres'])->toBe(1);
    expect($data[0]['en_espera'])->toBe(1);

    // Recargamos a Beto y promovemos: ahora si se le ofrece el lugar.
    $this->postJson("/api/v1/app/{$e['slug']}/derechos/{$b['derecho']}/topups", ['unidades' => 1000], conBearer($e['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/promover", [], conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.ofrecidas', 1);

    $this->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", conBearer($e['bearer']))
        ->assertOk()->assertJsonFragment(['estado' => 'ofrecida']);
});

it('smart-fill promover no ofrece nada si no hay lista de espera', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $ahora = Carbon::now('America/Mexico_City');

    $sesion = crearSesionTenant($e, $semilla, capacidad: 2, cuando: $ahora->copy()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'));
    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona']], conBearer($e['bearer']))->assertCreated();

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/promover", [], conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.ofrecidas', 0);
});

it('un instructor acotado solo ve sus propias clases en smart-fill', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $ahora = Carbon::now('America/Mexico_City');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    // Clase sin instructor asignado: el dueno la ve como oportunidad; el instructor no.
    crearSesionTenant($e, $semilla, capacidad: 3, cuando: $ahora->copy()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'));

    $this->getJson("/api/v1/app/{$e['slug']}/sesiones/oportunidades", conBearer($e['bearer']))
        ->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/app/{$e['slug']}/sesiones/oportunidades", conBearer($coach))
        ->assertOk()->assertJsonCount(0, 'data');
});

it('smart-fill promover exige permiso de gestion de reservas', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $ahora = Carbon::now('America/Mexico_City');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $sesion = crearSesionTenant($e, $semilla, capacidad: 2, cuando: $ahora->copy()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'));

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/promover", [], conBearer($coach))
        ->assertForbidden();
});
