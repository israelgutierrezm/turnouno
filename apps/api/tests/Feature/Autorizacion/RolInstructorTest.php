<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('el rol instructor ve su agenda y el roster pero no configura pasarelas ni crea productos', function (): void {
    $tenant = crearTenant('Pole House');
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);

    $instructor = User::factory()->create();
    vincularUsuario($tenant, $instructor, ['instructor']);

    Sanctum::actingAs($instructor);

    // Concedido: agenda.ver + reservas.ver.
    $this->getJson('/api/v1/mis-sesiones')->assertOk();
    $this->getJson("/api/v1/sesiones/{$sesion->ulid}/reservas")->assertOk();

    // Denegado: no tiene pagos.configurar ni productos.gestionar.
    $this->getJson('/api/v1/pasarelas')->assertStatus(403);
    $this->postJson('/api/v1/productos', [
        'nombre' => 'X',
        'tipo' => 'paquete',
        'precio_minor' => 0,
        'moneda' => 'MXN',
    ])->assertStatus(403);
});

it('el rol instructor puede marcar asistencia de una reserva confirmada', function (): void {
    $tenant = crearTenant('Pole House');
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);
    ['persona' => $persona] = participanteConDerecho($tenant, 8000);

    // Un recepcionista crea la reserva del participante.
    $recepcion = User::factory()->create();
    vincularUsuario($tenant, $recepcion, ['recepcionista']);
    Sanctum::actingAs($recepcion);
    $reservaId = $this->postJson("/api/v1/sesiones/{$sesion->ulid}/reservas", [
        'persona_id' => $persona->ulid,
    ])->assertCreated()->json('data.id');

    // El instructor marca asistencia (asistencia.registrar).
    $instructor = User::factory()->create();
    vincularUsuario($tenant, $instructor, ['instructor']);
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/reservas/{$reservaId}/asistencia", ['estado' => 'presente'])
        ->assertOk()
        ->assertJsonPath('data.asistencia', 'presente');
});
