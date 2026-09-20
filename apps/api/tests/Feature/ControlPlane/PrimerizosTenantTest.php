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

it('el roster marca primera_vez hasta que la persona asiste por primera vez', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $vp = venderPackAMiembroTenant($e, 8000, 'Ana');

    // Primera sesion: Ana nunca ha asistido -> primera_vez = true.
    $sesion1 = crearSesionTenant($e, $semilla);
    $reserva = (string) $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion1}/reservas", ['persona_id' => $vp['persona']], conBearer($e['bearer']))
        ->assertCreated()->assertJsonPath('data.primera_vez', true)->json('data.id');

    $roster = $this->getJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion1}/reservas", conBearer($e['bearer']))->assertOk()->json('data');
    expect($roster[0]['primera_vez'])->toBeTrue();

    // Asiste (presente) -> ya no es primeriza.
    $this->postJson("/api/v1/app/{$e['slug']}/reservas/{$reserva}/asistencia", ['estado' => 'presente'], conBearer($e['bearer']))->assertCreated();

    // Segunda sesion: ya asistio antes -> primera_vez = false.
    $sesion2 = crearSesionTenant($e, $semilla);
    $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion2}/reservas", ['persona_id' => $vp['persona']], conBearer($e['bearer']))
        ->assertCreated()->assertJsonPath('data.primera_vez', false);
});

it('el directorio de miembros marca primerizo y cuenta asistencias', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $vp = venderPackAMiembroTenant($e, 8000, 'Ana');

    // Sin asistencias: primeriza.
    $ana = collect($this->getJson("/api/v1/app/{$e['slug']}/miembros?tipo=miembro", conBearer($e['bearer']))->json('data'))
        ->firstWhere('nombre_completo', 'Ana');
    expect($ana['primera_vez'])->toBeTrue();
    expect($ana['asistencias'])->toBe(0);

    // Asiste una clase.
    $sesion = crearSesionTenant($e, $semilla);
    $reserva = (string) $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $vp['persona']], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $this->postJson("/api/v1/app/{$e['slug']}/reservas/{$reserva}/asistencia", ['estado' => 'presente'], conBearer($e['bearer']))->assertCreated();

    // Ya no es primeriza; cuenta 1 asistencia.
    $ana = collect($this->getJson("/api/v1/app/{$e['slug']}/miembros?tipo=miembro", conBearer($e['bearer']))->json('data'))
        ->firstWhere('nombre_completo', 'Ana');
    expect($ana['primera_vez'])->toBeFalse();
    expect($ana['asistencias'])->toBe(1);
});
