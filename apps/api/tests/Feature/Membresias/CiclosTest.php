<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\Application\GenerarCicloEntitlement;
use App\Modules\Membresias\Models\Acuerdo;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Crea un derecho con configuración de ciclo/rollover y un saldo inicial.
 *
 * @param  array<string, mixed>  $config
 */
function crearDerechoCiclo(Tenant $tenant, array $config, int $concesionInicial): Derecho
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);

    $producto = ProductoComercial::create([
        'nombre' => 'Membresía '.Str::random(4),
        'tipo' => 'membresia',
        'precio_minor' => 0,
        'moneda' => 'MXN',
        'ilimitado' => false,
        'creditos_incluidos' => null,
    ]);
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    $acuerdo = Acuerdo::create([
        'persona_id' => $persona->id,
        'producto_comercial_id' => $producto->id,
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'activo',
    ]);
    $derecho = $acuerdo->derechos()->create(array_merge(['ambito' => 'general', 'ilimitado' => false], $config));

    if ($concesionInicial > 0) {
        app(LibroMayor::class)->registrar($derecho, TipoMovimiento::Concesion, $concesionInicial, 'Concesión inicial');
    }

    $contexto->clear();

    return $derecho;
}

function saldoDe(Tenant $tenant, Derecho $derecho): int
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);
    $saldo = app(LibroMayor::class)->saldo($derecho);
    $contexto->clear();

    return $saldo;
}

function avanzarCiclos(Tenant $tenant, Derecho $derecho): int
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);
    $ciclos = app(GenerarCicloEntitlement::class)->ejecutar($derecho);
    $contexto->clear();

    return $ciclos;
}

it('reinicia el saldo por ciclo de calendario sin rollover', function (): void {
    $tenant = crearTenant('Pole House');
    $derecho = crearDerechoCiclo($tenant, [
        'politica_reset' => 'calendario',
        'unidades_por_ciclo' => 4000,
        'politica_rollover' => 'ninguno',
        'ciclo_inicio' => '2026-08-01',
        'ciclo_fin' => '2026-08-31',
    ], 4000);

    // Hoy (2026-09-14) > fin de agosto → avanza un ciclo.
    expect(avanzarCiclos($tenant, $derecho))->toBe(1);
    expect(saldoDe($tenant, $derecho))->toBe(4000); // lo viejo expiró, se concede el nuevo cupo
});

it('reinicia el saldo por ciclo de aniversario', function (): void {
    $tenant = crearTenant('Pole House');
    $derecho = crearDerechoCiclo($tenant, [
        'politica_reset' => 'aniversario',
        'unidades_por_ciclo' => 3000,
        'politica_rollover' => 'ninguno',
        'ciclo_inicio' => '2026-08-10',
        'ciclo_fin' => '2026-09-09',
    ], 3000);

    expect(avanzarCiclos($tenant, $derecho))->toBe(1);
    expect(saldoDe($tenant, $derecho))->toBe(3000);
    expect($derecho->refresh()->ciclo_inicio?->toDateString())->toBe('2026-09-10');
});

it('acarrea todo el saldo con rollover completo', function (): void {
    $tenant = crearTenant('Pole House');
    $derecho = crearDerechoCiclo($tenant, [
        'politica_reset' => 'calendario',
        'unidades_por_ciclo' => 4000,
        'politica_rollover' => 'completo',
        'ciclo_inicio' => '2026-08-01',
        'ciclo_fin' => '2026-08-31',
    ], 4000);

    // Consume 1000 antes del cierre (saldo 3000).
    app(TenantContext::class)->set($tenant);
    app(LibroMayor::class)->registrar($derecho, TipoMovimiento::Consumo, -1000, 'Consumo');
    app(TenantContext::class)->clear();

    avanzarCiclos($tenant, $derecho);
    expect(saldoDe($tenant, $derecho))->toBe(7000); // 3000 acarreado + 4000 nuevo
});

it('acarrea con tope (rollover limitado)', function (): void {
    $tenant = crearTenant('Pole House');
    $derecho = crearDerechoCiclo($tenant, [
        'politica_reset' => 'calendario',
        'unidades_por_ciclo' => 4000,
        'politica_rollover' => 'limitado',
        'rollover_max' => 2000,
        'ciclo_inicio' => '2026-08-01',
        'ciclo_fin' => '2026-08-31',
    ], 4000);

    // saldo 3000 al cierre; acarrea min(3000, 2000) = 2000.
    app(TenantContext::class)->set($tenant);
    app(LibroMayor::class)->registrar($derecho, TipoMovimiento::Consumo, -1000, 'Consumo');
    app(TenantContext::class)->clear();

    avanzarCiclos($tenant, $derecho);
    expect(saldoDe($tenant, $derecho))->toBe(6000); // 2000 acarreado + 4000 nuevo
});

it('es idempotente cuando el ciclo esta al dia', function (): void {
    $tenant = crearTenant('Pole House');
    $derecho = crearDerechoCiclo($tenant, [
        'politica_reset' => 'calendario',
        'unidades_por_ciclo' => 4000,
        'politica_rollover' => 'ninguno',
        'ciclo_inicio' => '2026-08-01',
        'ciclo_fin' => '2026-08-31',
    ], 4000);

    avanzarCiclos($tenant, $derecho);
    $derecho->refresh();

    // Segunda corrida: ya está al día, no avanza ni cambia el saldo.
    expect(avanzarCiclos($tenant, $derecho))->toBe(0);
    expect(saldoDe($tenant, $derecho))->toBe(4000);
});

it('suma un top-up al derecho como asiento separado', function (): void {
    $tenant = crearTenant('Pole House');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);
    $derecho = crearDerechoConCreditos($tenant, 8000);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/derechos/{$derecho->ulid}/topups", ['unidades' => 2000])
        ->assertCreated()
        ->assertJsonPath('data.saldo_unidades', 10000);
});
