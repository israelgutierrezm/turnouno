<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use Laravel\Sanctum\Sanctum;

/**
 * Crea una plantilla Lun/Mié 19:00 de 60 min vigente desde el 2026-10-01 y
 * devuelve su ulid. Requiere una sesión autenticada con permiso agenda.gestionar.
 */
function crearPlantillaLunMie(string $ofertaUlid, string $sucursalUlid): string
{
    return test()->postJson("/api/v1/ofertas/{$ofertaUlid}/plantillas-horario", [
        'sucursal_id' => $sucursalUlid,
        'duracion_minutos' => 60,
        'vigente_desde' => '2026-10-01',
        'reglas' => [
            ['dia_semana' => 1, 'hora_inicio' => '19:00'],
            ['dia_semana' => 3, 'hora_inicio' => '19:00'],
        ],
    ])->assertCreated()->json('data.id');
}

it('materializa sesiones desde la plantilla y es idempotente', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $plantillaUlid = crearPlantillaLunMie($oferta->ulid, $sucursal->ulid);

    // Rango 2026-10-05 (lunes) … 2026-10-18 (domingo): Lun 5, Mié 7, Lun 12, Mié 14 = 4.
    $this->postJson("/api/v1/plantillas-horario/{$plantillaUlid}/sesiones", [
        'desde' => '2026-10-05',
        'hasta' => '2026-10-18',
    ])->assertCreated()->assertJsonPath('data.creadas', 4);

    // Reejecutar el mismo rango no duplica sesiones (idempotente).
    $this->postJson("/api/v1/plantillas-horario/{$plantillaUlid}/sesiones", [
        'desde' => '2026-10-05',
        'hasta' => '2026-10-18',
    ])->assertCreated()->assertJsonPath('data.creadas', 0);

    $this->getJson("/api/v1/sucursales/{$sucursal->ulid}/sesiones?desde=2026-10-05&hasta=2026-10-18")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('guarda la hora de inicio en UTC segun la zona de la sucursal', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    // Bogotá es UTC-5 fijo (sin horario de verano): 07:00 local = 12:00 UTC.
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant, 'America/Bogota');

    Sanctum::actingAs($user);

    $plantillaUlid = $this->postJson("/api/v1/ofertas/{$oferta->ulid}/plantillas-horario", [
        'sucursal_id' => $sucursal->ulid,
        'duracion_minutos' => 60,
        'vigente_desde' => '2026-10-01',
        'reglas' => [['dia_semana' => 1, 'hora_inicio' => '07:00']],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/plantillas-horario/{$plantillaUlid}/sesiones", [
        'desde' => '2026-10-05',
        'hasta' => '2026-10-05',
    ])->assertCreated()->assertJsonPath('data.creadas', 1);

    $sesion = $this->getJson("/api/v1/sucursales/{$sucursal->ulid}/sesiones?desde=2026-10-05&hasta=2026-10-05")
        ->assertOk()
        ->json('data.0');

    expect($sesion['inicia_en'])->toBe('2026-10-05T12:00:00+00:00');
    expect($sesion['termina_en'])->toBe('2026-10-05T13:00:00+00:00');
    expect($sesion['zona_horaria'])->toBe('America/Bogota');
});

it('crea una sesion unica ad-hoc', function (): void {
    $tenant = crearTenant('Aqua');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sucursales/{$sucursal->ulid}/sesiones", [
        'oferta_id' => $oferta->ulid,
        'inicia_en_local' => '2026-10-10 09:00',
        'duracion_minutos' => 45,
    ])->assertCreated()->assertJsonPath('data.estado', 'programada');

    $this->getJson("/api/v1/sucursales/{$sucursal->ulid}/sesiones?desde=2026-10-10&hasta=2026-10-10")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('cancela una sesion', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $plantillaUlid = crearPlantillaLunMie($oferta->ulid, $sucursal->ulid);
    $this->postJson("/api/v1/plantillas-horario/{$plantillaUlid}/sesiones", [
        'desde' => '2026-10-05',
        'hasta' => '2026-10-05',
    ])->assertCreated();

    $sesionUlid = $this->getJson("/api/v1/sucursales/{$sucursal->ulid}/sesiones?desde=2026-10-05&hasta=2026-10-05")
        ->json('data.0.id');

    $this->postJson("/api/v1/sesiones/{$sesionUlid}/cancelar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelada');
});

it('asigna un instructor y este ve la sesion en su agenda, aislada por usuario', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);

    $instructor = User::factory()->create();
    vincularUsuario($tenant, $instructor, ['recepcionista']);
    // vincularUsuario ya enlaza la Persona del usuario en el tenant; la usamos.
    $persona = Persona::withoutGlobalScope('tenant')
        ->where('user_id', $instructor->id)
        ->firstOrFail();

    Sanctum::actingAs($owner);
    $plantillaUlid = crearPlantillaLunMie($oferta->ulid, $sucursal->ulid);
    $this->postJson("/api/v1/plantillas-horario/{$plantillaUlid}/sesiones", [
        'desde' => '2026-10-05',
        'hasta' => '2026-10-05',
    ])->assertCreated();
    $sesionUlid = $this->getJson("/api/v1/sucursales/{$sucursal->ulid}/sesiones?desde=2026-10-05&hasta=2026-10-05")
        ->json('data.0.id');

    $this->postJson("/api/v1/sesiones/{$sesionUlid}/asignaciones", [
        'persona_id' => $persona->ulid,
    ])->assertCreated();

    // El instructor ve su sesión.
    Sanctum::actingAs($instructor);
    $this->getJson('/api/v1/mis-sesiones')->assertOk()->assertJsonCount(1, 'data');

    // El dueño (sin persona vinculada) no la ve en su agenda personal.
    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/mis-sesiones')->assertOk()->assertJsonCount(0, 'data');
});

it('no muestra sesiones de una sucursal de otro tenant', function (): void {
    $tenantA = crearTenant('Tenant A');
    $usuarioA = User::factory()->create();
    vincularUsuario($tenantA, $usuarioA, ['propietario']);

    $tenantB = crearTenant('Tenant B');
    app(TenantContext::class)->set($tenantB);
    $organizacionB = app(CrearOrganizacion::class)->ejecutar('Org B');
    $sucursalB = app(CrearSucursal::class)->ejecutar($organizacionB, 'Sur');
    app(TenantContext::class)->clear();

    Sanctum::actingAs($usuarioA);

    $this->getJson("/api/v1/sucursales/{$sucursalB->ulid}/sesiones")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('prohibe crear una plantilla sin el permiso agenda.gestionar', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['miembro']);
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/ofertas/{$oferta->ulid}/plantillas-horario", [
        'sucursal_id' => $sucursal->ulid,
        'duracion_minutos' => 60,
        'vigente_desde' => '2026-10-01',
        'reglas' => [['dia_semana' => 1, 'hora_inicio' => '19:00']],
    ])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
});
