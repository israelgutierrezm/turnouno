<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Catalogo\Models\Programa;
use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('un derecho restringido a una actividad no cubre otra actividad', function (): void {
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);
    ['oferta' => $ofertaA, 'sucursal' => $sucursal] = crearOfertaYSucursal($tenant);

    app(TenantContext::class)->set($tenant);
    $programaB = Programa::create(['nombre' => 'Yoga', 'slug' => 'yoga-'.Str::lower(Str::random(5))]);
    $actividadB = Actividad::create(['programa_id' => $programaB->id, 'nombre' => 'Yoga Flow', 'slug' => 'yf-'.Str::lower(Str::random(5))]);
    $ofertaB = Oferta::create(['actividad_id' => $actividadB->id, 'nombre' => 'Clase yoga', 'modalidad' => 'grupal', 'capacidad' => 8]);

    // Membresía ilimitada restringida a la actividad A.
    $producto = ProductoComercial::create([
        'nombre' => 'Membresía Pole',
        'tipo' => 'membresia',
        'precio_minor' => 0,
        'moneda' => 'MXN',
        'ilimitado' => true,
        'actividad_id' => $ofertaA->actividad_id,
    ]);
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    app(CrearAcuerdo::class)->ejecutar($persona, $producto);
    app(TenantContext::class)->clear();

    $sesionA = crearSesion($tenant, $sucursal, $ofertaA, 8);
    $sesionB = crearSesion($tenant, $sucursal, $ofertaB, 8);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/sesiones/{$sesionA->ulid}/reservas", ['persona_id' => $persona->ulid])
        ->assertCreated();

    $this->postJson("/api/v1/sesiones/{$sesionB->ulid}/reservas", ['persona_id' => $persona->ulid])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ENTITLEMENT_REQUIRED');
});

it('un derecho restringido a una sucursal no cubre otra sucursal', function (): void {
    $tenant = crearTenant('AquaKids');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    app(TenantContext::class)->set($tenant);
    $organizacion = app(CrearOrganizacion::class)->ejecutar('Central');
    $sucursal1 = app(CrearSucursal::class)->ejecutar($organizacion, 'Norte', 'America/Mexico_City');
    $sucursal2 = app(CrearSucursal::class)->ejecutar($organizacion, 'Sur', 'America/Mexico_City');

    $programa = Programa::create(['nombre' => 'Natación', 'slug' => 'nat-'.Str::lower(Str::random(5))]);
    $actividad = Actividad::create(['programa_id' => $programa->id, 'nombre' => 'Natación', 'slug' => 'na-'.Str::lower(Str::random(5))]);
    $oferta = Oferta::create(['actividad_id' => $actividad->id, 'nombre' => 'Clase', 'modalidad' => 'grupal', 'capacidad' => 8]);

    // Membresía ilimitada restringida a la sucursal 1.
    $producto = ProductoComercial::create([
        'nombre' => 'Membresía Norte',
        'tipo' => 'membresia',
        'precio_minor' => 0,
        'moneda' => 'MXN',
        'ilimitado' => true,
        'sucursal_id' => $sucursal1->id,
    ]);
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    app(CrearAcuerdo::class)->ejecutar($persona, $producto);
    app(TenantContext::class)->clear();

    $sesion1 = crearSesion($tenant, $sucursal1, $oferta, 8);
    $sesion2 = crearSesion($tenant, $sucursal2, $oferta, 8);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/sesiones/{$sesion1->ulid}/reservas", ['persona_id' => $persona->ulid])
        ->assertCreated();

    $this->postJson("/api/v1/sesiones/{$sesion2->ulid}/reservas", ['persona_id' => $persona->ulid])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ENTITLEMENT_REQUIRED');
});
