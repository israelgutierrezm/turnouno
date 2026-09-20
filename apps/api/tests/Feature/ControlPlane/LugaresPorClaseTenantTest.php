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
 * Fija el número de lugares de la oferta de la semilla.
 *
 * @param  array{slug: string, bearer: string}  $e
 * @param  array{oferta: string, sucursal: string}  $semilla
 */
function fijarLugares(array $e, array $semilla, int $lugares): void
{
    test()->putJson("/api/v1/app/{$e['slug']}/ofertas/{$semilla['oferta']}", ['lugares' => $lugares], conBearer($e['bearer']))
        ->assertOk()->assertJsonPath('data.lugares', $lugares);
}

it('reserva con un lugar elegido y lo muestra en el roster', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    fijarLugares($e, $semilla, 5);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 10);
    $vp = venderPackAMiembroTenant($e, 8000, 'Ana');

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", [
        'persona_id' => $vp['persona'], 'lugar' => 3,
    ], conBearer($e['bearer']))->assertCreated()->assertJsonPath('data.lugar', 3);

    $roster = $this->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", conBearer($e['bearer']))->assertOk()->json('data');
    expect($roster[0]['lugar'])->toBe(3);
});

it('rechaza un lugar ya tomado o fuera de rango (SPOT_UNAVAILABLE)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    fijarLugares($e, $semilla, 5);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 10);
    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $b = venderPackAMiembroTenant($e, 8000, 'Beto');

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona'], 'lugar' => 3], conBearer($e['bearer']))->assertCreated();

    // Mismo lugar: ocupado.
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona'], 'lugar' => 3], conBearer($e['bearer']))
        ->assertStatus(422)->assertJsonPath('code', 'SPOT_UNAVAILABLE');

    // Fuera de rango (>5).
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona'], 'lugar' => 6], conBearer($e['bearer']))
        ->assertStatus(422)->assertJsonPath('code', 'SPOT_UNAVAILABLE');

    // Otro lugar libre: ok.
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $b['persona'], 'lugar' => 2], conBearer($e['bearer']))
        ->assertCreated()->assertJsonPath('data.lugar', 2);
});

it('una clase sin lugares ignora el lugar enviado', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e); // oferta con lugares = 0 por defecto
    $sesion = crearSesionTenant($e, $semilla, capacidad: 10);
    $vp = venderPackAMiembroTenant($e, 8000, 'Ana');

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", [
        'persona_id' => $vp['persona'], 'lugar' => 2,
    ], conBearer($e['bearer']))->assertCreated()->assertJsonPath('data.lugar', null);
});

it('el preview marca el lugar ocupado sin crear la reserva', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    fijarLugares($e, $semilla, 4);
    $sesion = crearSesionTenant($e, $semilla, capacidad: 10);
    $a = venderPackAMiembroTenant($e, 8000, 'Ana');
    $b = venderPackAMiembroTenant($e, 8000, 'Beto');

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $a['persona'], 'lugar' => 1], conBearer($e['bearer']))->assertCreated();

    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas/preview", [
        'persona_id' => $b['persona'], 'lugar' => 1,
    ], conBearer($e['bearer']))->assertOk()
        ->assertJsonPath('data.permitida', false)
        ->assertJsonPath('data.reason_code', 'SPOT_UNAVAILABLE');
});
