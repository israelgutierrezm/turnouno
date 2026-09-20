<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Creditos\LibroMayor;
use Laravel\Sanctum\Sanctum;

/*
| Escenarios de aceptación de los tres verticales piloto (DEVELOPMENT_PLAN Slice 8).
| Prueban que UN mismo core configurable cubre las tres operativas sin ramas por
| industria: cambia la configuración (tipo de producto, capacidad, participante),
| no el motor. Cada prueba recorre el flujo crítico end-to-end vía la API.
*/

it('pole: pack de créditos, clase grupal, reserva con hold y asistencia', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant, 'America/Mexico_City', 8);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);
    ['persona' => $miembro, 'derecho' => $derecho] = participanteConDerecho($tenant, 8000);

    Sanctum::actingAs($owner);

    $reserva = $this->postJson("/api/v1/sesiones/{$sesion->ulid}/reservas", ['persona_id' => $miembro->ulid])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'confirmada')
        ->assertJsonPath('data.unidades', 1000)
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'presente'])
        ->assertOk()
        ->assertJsonPath('data.asistencia', 'presente');

    // Marcar presente consume el crédito retenido: el servicio fue prestado (F-02).
    $libro = app(LibroMayor::class);
    expect($libro->saldo($derecho))->toBe(7000);
    expect($libro->disponible($derecho))->toBe(7000);
});

it('natación: clase reducida y reserva del menor', function (): void {
    $tenant = crearTenant('AquaKids');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    // Clase privada/reducida (capacidad 3) — típico de natación.
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant, 'America/Mexico_City', 3);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 3);

    Sanctum::actingAs($owner);

    // El menor participa; el derecho (pack) vive en el participante.
    $menor = $this->postJson('/api/v1/personas', ['nombre' => 'Sofía', 'apellidos' => 'López'])
        ->assertCreated()->json('data.id');

    $producto = $this->postJson('/api/v1/productos', [
        'nombre' => 'Pack natación 4',
        'tipo' => 'paquete',
        'precio_minor' => 50000,
        'moneda' => 'MXN',
        'ilimitado' => false,
        'creditos_incluidos' => 4000,
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/personas/{$menor}/acuerdos", ['producto_id' => $producto])->assertCreated();

    // El menor reserva y se registra su asistencia.
    $reserva = $this->postJson("/api/v1/sesiones/{$sesion->ulid}/reservas", ['persona_id' => $menor])
        ->assertCreated()
        ->assertJsonPath('data.unidades', 1000)
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'presente'])->assertOk();

    $this->getJson("/api/v1/sesiones/{$sesion->ulid}/reservas")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.asistencia', 'presente');
});

it('gym: membresía ilimitada, reserva sin hold en varias sesiones', function (): void {
    $tenant = crearTenant('Iron Gym');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant, 'America/Mexico_City', 20);
    $lunes = crearSesion($tenant, $sucursal, $oferta, 20, '2026-10-05 07:00');
    $martes = crearSesion($tenant, $sucursal, $oferta, 20, '2026-10-06 07:00');
    ['persona' => $miembro, 'derecho' => $derecho] = participanteConDerecho($tenant, 0, true);

    Sanctum::actingAs($owner);

    // Membresía ilimitada: reserva sin retener créditos (unidades 0) y en varias sesiones.
    $this->postJson("/api/v1/sesiones/{$lunes->ulid}/reservas", ['persona_id' => $miembro->ulid])
        ->assertCreated()
        ->assertJsonPath('data.unidades', 0);

    $this->postJson("/api/v1/sesiones/{$martes->ulid}/reservas", ['persona_id' => $miembro->ulid])
        ->assertCreated()
        ->assertJsonPath('data.unidades', 0);

    // Sin ledger para un derecho ilimitado.
    expect(app(LibroMayor::class)->saldo($derecho))->toBe(0);
});
