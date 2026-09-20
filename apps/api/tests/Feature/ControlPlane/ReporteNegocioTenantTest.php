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

it('el reporte de negocio agrega ingresos, ocupacion, no-show, alumnos activos y ARPU', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);
    $ahora = Carbon::now('America/Mexico_City');

    // Clase futura con cupo 4, un alumno reservado y presente.
    $cuando = $ahora->copy()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s');
    $sesion = crearSesionTenant($e, $semilla, capacidad: 4, cuando: $cuando);
    $vp = venderPackAMiembroTenant($e, 8000, 'Ana');

    $reserva = (string) $this->postJson("/api/v1/app/{$e['slug']}/sesiones/{$sesion}/reservas", ['persona_id' => $vp['persona']], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $this->postJson("/api/v1/app/{$e['slug']}/reservas/{$reserva}/asistencia", ['estado' => 'presente'], conBearer($e['bearer']))->assertCreated();

    // Ingresos: una orden liquidada en ventanilla (efectivo).
    $producto = crearPackTenant($e);
    $orden = (string) $this->postJson("/api/v1/app/{$e['slug']}/ordenes", [
        'comprador_id' => $vp['persona'],
        'items' => [['producto_id' => $producto, 'cantidad' => 1]],
    ], conBearer($e['bearer']))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/app/{$e['slug']}/ordenes/{$orden}/liquidar", ['metodo' => 'efectivo'], conBearer($e['bearer']))->assertOk();

    $desde = $ahora->copy()->subMonth()->toDateString();
    $hasta = $ahora->copy()->addMonth()->toDateString();
    $r = $this->getJson("/api/v1/app/{$e['slug']}/reportes/negocio?desde={$desde}&hasta={$hasta}", conBearer($e['bearer']))
        ->assertOk()->json('data');

    expect($r['ingresos_minor'])->toBe(89900);
    expect($r['ordenes_pagadas'])->toBe(1);
    expect($r['clases'])->toBe(1);
    expect($r['confirmadas'])->toBe(1);
    expect($r['ocupacion_pct'])->toBe(25); // 1 de 4
    expect($r['alumnos_activos'])->toBe(1);
    expect($r['presentes'])->toBe(1);
    expect($r['ausentes'])->toBe(0);
    expect($r['no_show_pct'])->toBe(0);
    expect($r['arpu_minor'])->toBe(89900);
});

it('el reporte de negocio exige permiso de facturacion', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $hoy = Carbon::now()->toDateString();
    $this->getJson("/api/v1/app/{$e['slug']}/reportes/negocio?desde={$hoy}&hasta={$hoy}", conBearer($coach))
        ->assertForbidden();
});
