<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Agenda\Application\CancelarSesion;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

/**
 * Arma un escenario de reserva: tenant + owner con permiso, oferta/sucursal,
 * una sesión reservable y un participante con derecho.
 *
 * @return array{tenant: Tenant, owner: User, sesion: Sesion, persona: Persona, derecho: Derecho}
 */
function escenarioReserva(?int $capacidad = 8, int $unidades = 8000, bool $ilimitado = false, string $cuando = '2026-10-05 19:00'): array
{
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, $capacidad, $cuando);
    ['persona' => $persona, 'derecho' => $derecho] = participanteConDerecho($tenant, $unidades, $ilimitado);

    return compact('tenant', 'owner', 'sesion', 'persona', 'derecho');
}

it('confirma la reserva, retiene el credito y aparece en el roster', function (): void {
    $e = escenarioReserva();
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'confirmada')
        ->assertJsonPath('data.unidades', 1000);

    $libro = app(LibroMayor::class);
    expect($libro->saldo($e['derecho']))->toBe(8000);        // el hold no consume el ledger
    expect($libro->disponible($e['derecho']))->toBe(7000);   // pero sí reduce lo disponible

    $this->getJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rechaza la reserva cuando la sesion esta llena', function (): void {
    $e = escenarioReserva(capacidad: 1);
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated();

    ['persona' => $otra] = participanteConDerecho($e['tenant']);
    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $otra->ulid])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CAPACITY_FULL');
});

it('rechaza la reserva si la persona no tiene derecho', function (): void {
    $e = escenarioReserva();
    $sinDerecho = Persona::factory()->create(['tenant_id' => $e['tenant']->id]);

    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $sinDerecho->ulid])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ENTITLEMENT_REQUIRED');
});

it('rechaza la reserva si el derecho no tiene saldo suficiente', function (): void {
    $e = escenarioReserva(unidades: 500); // menos que el costo (1000)
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ENTITLEMENT_REQUIRED');
});

it('rechaza una segunda reserva de la misma persona en la sesion', function (): void {
    $e = escenarioReserva();
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated();

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertStatus(409)
        ->assertJsonPath('code', 'ALREADY_BOOKED');
});

it('es idempotente con la misma idempotency_key', function (): void {
    $e = escenarioReserva();
    Sanctum::actingAs($e['owner']);

    $primera = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", [
        'persona_id' => $e['persona']->ulid,
        'idempotency_key' => 'reserva-abc',
    ])->assertCreated()->json('data.id');

    $segunda = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", [
        'persona_id' => $e['persona']->ulid,
        'idempotency_key' => 'reserva-abc',
    ])->assertCreated()->json('data.id');

    expect($segunda)->toBe($primera);
    // El crédito se retuvo una sola vez.
    expect(app(LibroMayor::class)->disponible($e['derecho']))->toBe(7000);
});

it('cancelar a tiempo libera el credito retenido', function (): void {
    $e = escenarioReserva(); // sesión lejana (> 6h)
    Sanctum::actingAs($e['owner']);

    $reservaUlid = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reservaUlid}/cancelar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelada');

    $libro = app(LibroMayor::class);
    expect($libro->saldo($e['derecho']))->toBe(8000);
    expect($libro->disponible($e['derecho']))->toBe(8000); // el crédito volvió
});

it('cancelar tarde penaliza consumiendo el credito', function (): void {
    $cuandoPronto = CarbonImmutable::now('America/Mexico_City')->addHours(2)->format('Y-m-d H:i');
    $e = escenarioReserva(cuando: $cuandoPronto); // dentro de la ventana de penalización (< 6h)
    Sanctum::actingAs($e['owner']);

    $reservaUlid = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reservaUlid}/cancelar")->assertOk();

    $libro = app(LibroMayor::class);
    expect($libro->saldo($e['derecho']))->toBe(7000);      // se consumió (penalización)
    expect($libro->disponible($e['derecho']))->toBe(7000);
});

it('no permite reservar una sesion cancelada', function (): void {
    $e = escenarioReserva();

    app(TenantContext::class)->set($e['tenant']);
    app(CancelarSesion::class)->ejecutar($e['sesion']);
    app(TenantContext::class)->clear();

    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SESSION_NOT_BOOKABLE');
});

it('reserva con derecho ilimitado sin retener credito', function (): void {
    $e = escenarioReserva(ilimitado: true);
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'confirmada')
        ->assertJsonPath('data.unidades', 0);
});

it('exige el permiso reservas.crear', function (): void {
    $tenant = crearTenant('Acme');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, []); // sin roles ni permisos
    ['oferta' => $oferta, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);
    $sesion = crearSesion($tenant, $sucursal, $oferta, 8);
    ['persona' => $persona] = participanteConDerecho($tenant);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sesiones/{$sesion->ulid}/reservas", ['persona_id' => $persona->ulid])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('pone en lista de espera cuando la sesion esta llena y se pidio esperar', function (): void {
    $e = escenarioReserva(capacidad: 1);
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated();

    ['persona' => $otra, 'derecho' => $derechoOtra] = participanteConDerecho($e['tenant']);
    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", [
        'persona_id' => $otra->ulid,
        'esperar' => true,
    ])->assertCreated()->assertJsonPath('data.estado', 'en_espera');

    // En espera no retiene crédito.
    expect(app(LibroMayor::class)->disponible($derechoOtra))->toBe(8000);
    // El roster muestra confirmada + en espera.
    $this->getJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas")->assertOk()->assertJsonCount(2, 'data');
});

it('promueve al siguiente de la lista de espera al cancelar', function (): void {
    $e = escenarioReserva(capacidad: 1);
    Sanctum::actingAs($e['owner']);

    $reservaA = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->json('data.id');

    ['persona' => $otra, 'derecho' => $derechoOtra] = participanteConDerecho($e['tenant']);
    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", [
        'persona_id' => $otra->ulid,
        'esperar' => true,
    ])->assertCreated();

    $this->postJson("/api/v1/reservas/{$reservaA}/cancelar")->assertOk();

    // A liberó su crédito; B fue promovida y ahora retiene el suyo.
    expect(app(LibroMayor::class)->disponible($e['derecho']))->toBe(8000);
    expect(app(LibroMayor::class)->disponible($derechoOtra))->toBe(7000);

    // El roster queda con una sola reserva: B confirmada.
    $this->getJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado', 'confirmada');
});

it('registra la asistencia de una reserva confirmada', function (): void {
    $e = escenarioReserva();
    Sanctum::actingAs($e['owner']);

    $reserva = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'presente'])
        ->assertOk()
        ->assertJsonPath('data.asistencia', 'presente');

    $this->getJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas")
        ->assertOk()
        ->assertJsonPath('data.0.asistencia', 'presente');

    // F-02: presente consume el crédito retenido (servicio prestado).
    $libro = app(LibroMayor::class);
    expect($libro->saldo($e['derecho']))->toBe(7000);
    expect($libro->disponible($e['derecho']))->toBe(7000);
});

it('marcar presente es idempotente: no consume dos veces (F-02)', function (): void {
    $e = escenarioReserva();
    Sanctum::actingAs($e['owner']);

    $reserva = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'presente'])->assertOk();
    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'presente'])->assertOk();

    $libro = app(LibroMayor::class);
    expect($libro->saldo($e['derecho']))->toBe(7000);      // consumido una sola vez
    expect($libro->disponible($e['derecho']))->toBe(7000);
});

it('marcar ausente pierde el credito retenido (no-show, F-02)', function (): void {
    $e = escenarioReserva();
    Sanctum::actingAs($e['owner']);

    $reserva = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->json('data.id');

    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'ausente'])
        ->assertOk()
        ->assertJsonPath('data.asistencia', 'ausente');

    $libro = app(LibroMayor::class);
    expect($libro->saldo($e['derecho']))->toBe(7000);      // forfeit
    expect($libro->disponible($e['derecho']))->toBe(7000);
});

it('marcar asistencia con derecho ilimitado no toca el ledger (F-02)', function (): void {
    $e = escenarioReserva(ilimitado: true);
    Sanctum::actingAs($e['owner']);

    $reserva = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/reservas/{$reserva}/asistencia", ['estado' => 'presente'])
        ->assertOk()
        ->assertJsonPath('data.asistencia', 'presente');

    // Sin retención (ilimitado): saldo y disponible permanecen en 0 sin error.
    $libro = app(LibroMayor::class);
    expect($libro->disponible($e['derecho']))->toBe(0);
});

it('no registra asistencia de una reserva en espera', function (): void {
    $e = escenarioReserva(capacidad: 1);
    Sanctum::actingAs($e['owner']);

    $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", ['persona_id' => $e['persona']->ulid])
        ->assertCreated();

    ['persona' => $otra] = participanteConDerecho($e['tenant']);
    $enEspera = $this->postJson("/api/v1/sesiones/{$e['sesion']->ulid}/reservas", [
        'persona_id' => $otra->ulid,
        'esperar' => true,
    ])->json('data.id');

    $this->postJson("/api/v1/reservas/{$enEspera}/asistencia", ['estado' => 'presente'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RESERVATION_NOT_CONFIRMED');
});
