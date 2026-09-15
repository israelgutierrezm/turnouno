<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Agenda\Models\AsignacionSesion;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Laravel\Sanctum\Sanctum;

/**
 * Asigna la persona del usuario como instructor de la sesión.
 */
function asignarComoInstructor(Tenant $tenant, Sesion $sesion, User $usuario): void
{
    app(TenantContext::class)->set($tenant);
    $persona = Persona::query()->withoutGlobalScope('tenant')->where('user_id', $usuario->id)->firstOrFail();
    AsignacionSesion::create(['sesion_id' => $sesion->id, 'persona_id' => $persona->id, 'rol' => 'instructor']);
    app(TenantContext::class)->clear();
}

it('el instructor ve su agenda y el roster de su sesión, pero no configura pasarelas ni crea productos', function (): void {
    $tenant = crearTenant('Pole House');
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);

    $instructor = User::factory()->create();
    vincularUsuario($tenant, $instructor, ['instructor']);
    asignarComoInstructor($tenant, $sesion, $instructor);

    Sanctum::actingAs($instructor);

    // Concedido: agenda.ver + roster de SU sesión.
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

it('el instructor marca asistencia de una reserva de su sesión asignada', function (): void {
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

    // El instructor asignado marca asistencia.
    $instructor = User::factory()->create();
    vincularUsuario($tenant, $instructor, ['instructor']);
    asignarComoInstructor($tenant, $sesion, $instructor);
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/reservas/{$reservaId}/asistencia", ['estado' => 'presente'])
        ->assertOk()
        ->assertJsonPath('data.asistencia', 'presente');
});

it('el instructor NO puede ver el roster ni marcar asistencia de una sesión no asignada (F-09/SEC-05)', function (): void {
    $tenant = crearTenant('Pole House');
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);
    ['persona' => $persona] = participanteConDerecho($tenant, 8000);

    $recepcion = User::factory()->create();
    vincularUsuario($tenant, $recepcion, ['recepcionista']);
    Sanctum::actingAs($recepcion);
    $reservaId = $this->postJson("/api/v1/sesiones/{$sesion->ulid}/reservas", [
        'persona_id' => $persona->ulid,
    ])->assertCreated()->json('data.id');

    // Instructor NO asignado a esta sesión.
    $instructor = User::factory()->create();
    vincularUsuario($tenant, $instructor, ['instructor']);
    Sanctum::actingAs($instructor);

    $this->getJson("/api/v1/sesiones/{$sesion->ulid}/reservas")
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->postJson("/api/v1/reservas/{$reservaId}/asistencia", ['estado' => 'presente'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});
