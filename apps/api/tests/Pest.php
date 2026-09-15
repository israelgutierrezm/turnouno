<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Agenda\Application\CrearSesionUnica;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Catalogo\Models\Programa;
use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Application\CrearTenant;
use App\Modules\Tenancy\Application\VincularUsuarioATenant;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
| Las pruebas Feature corren contra la base de datos de pruebas (MySQL) y la
| refrescan entre pruebas. Las pruebas Unit/arquitectura no tocan la base.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
| Helpers de dominio.
*/
function crearTenant(string $nombre = 'Estudio Acme'): Tenant
{
    return app(CrearTenant::class)->ejecutar($nombre);
}

/**
 * @param  list<string>  $roles
 */
function vincularUsuario(Tenant $tenant, User $user, array $roles = []): void
{
    app(VincularUsuarioATenant::class)->ejecutar($tenant, $user, $roles);
}

/**
 * Crea un producto limitado, lo vende a una persona nueva y devuelve el derecho
 * resultante con `$unidades` créditos concedidos en el ledger.
 */
function crearDerechoConCreditos(Tenant $tenant, int $unidades): Derecho
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $producto = app(CrearProducto::class)->ejecutar(
        'Producto '.Str::random(5),
        TipoProducto::Membresia,
        0,
        'MXN',
        false,
        $unidades,
    );
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    $acuerdo = app(CrearAcuerdo::class)->ejecutar($persona, $producto);
    $derecho = $acuerdo->derechos()->firstOrFail();

    $contexto->clear();

    return $derecho;
}

/**
 * Crea la cadena mínima para agenda dentro de un tenant: organización → sucursal
 * (con zona horaria) y programa → actividad → oferta.
 *
 * @return array{oferta: Oferta, sucursal: Sucursal}
 */
function crearOfertaYSucursal(Tenant $tenant, string $zona = 'America/Mexico_City', ?int $capacidad = 8): array
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $organizacion = app(CrearOrganizacion::class)->ejecutar('Central');
    $sucursal = app(CrearSucursal::class)->ejecutar($organizacion, 'Sucursal Centro', $zona);

    $programa = Programa::create(['nombre' => 'Pole', 'slug' => 'pole-'.Str::lower(Str::random(6))]);
    $actividad = Actividad::create([
        'programa_id' => $programa->id,
        'nombre' => 'Pole Fitness',
        'slug' => 'pf-'.Str::lower(Str::random(6)),
    ]);
    $oferta = Oferta::create([
        'actividad_id' => $actividad->id,
        'nombre' => 'Clase grupal',
        'modalidad' => 'grupal',
        'capacidad' => $capacidad,
    ]);

    $contexto->clear();

    return ['oferta' => $oferta, 'sucursal' => $sucursal];
}

/**
 * Crea una sesión única reservable en el tenant.
 */
function crearSesion(Tenant $tenant, Sucursal $sucursal, Oferta $oferta, ?int $capacidad = 8, string $cuando = '2026-10-05 19:00'): Sesion
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);

    $sesion = app(CrearSesionUnica::class)->ejecutar($oferta, $sucursal, [
        'inicia_en_local' => $cuando,
        'duracion_minutos' => 60,
        'capacidad' => $capacidad,
    ]);

    $contexto->clear();

    return $sesion;
}

/**
 * Devuelve la Persona vinculada a un usuario (su perfil de miembro).
 */
function personaDe(User $usuario): Persona
{
    return Persona::query()->withoutGlobalScope('tenant')->where('user_id', $usuario->id)->firstOrFail();
}

/**
 * Crea un tenant con su usuario propietario autenticable.
 *
 * @return array{tenant: Tenant, owner: User}
 */
function tenantConDueno(): array
{
    $tenant = crearTenant('Pole House');
    $owner = User::factory()->create();
    vincularUsuario($tenant, $owner, ['propietario']);

    return ['tenant' => $tenant, 'owner' => $owner];
}

/**
 * Crea (vía API) un producto tipo pack de 8 créditos y devuelve su ulid.
 * Requiere una sesión autenticada con permiso productos.gestionar.
 */
function crearProductoPack(): string
{
    return test()->postJson('/api/v1/productos', [
        'nombre' => 'Pack 8 clases',
        'tipo' => 'paquete',
        'precio_minor' => 89900,
        'moneda' => 'MXN',
        'ilimitado' => false,
        'creditos_incluidos' => 8000,
    ])->assertCreated()->json('data.id');
}

/**
 * Crea una persona (participante) con un derecho: por defecto un pack con
 * `$unidades` créditos; con `$ilimitado` un derecho sin saldo (membresía).
 *
 * @return array{persona: Persona, derecho: Derecho}
 */
function participanteConDerecho(Tenant $tenant, int $unidades = 8000, bool $ilimitado = false): array
{
    $contexto = app(TenantContext::class);
    $contexto->set($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $producto = app(CrearProducto::class)->ejecutar(
        'Producto '.Str::random(5),
        $ilimitado ? TipoProducto::Membresia : TipoProducto::Paquete,
        0,
        'MXN',
        $ilimitado,
        $ilimitado ? null : $unidades,
    );
    $persona = Persona::factory()->create(['tenant_id' => $tenant->id]);
    $acuerdo = app(CrearAcuerdo::class)->ejecutar($persona, $producto);
    $derecho = $acuerdo->derechos()->firstOrFail();

    $contexto->clear();

    return ['persona' => $persona, 'derecho' => $derecho];
}

/*
| Helpers del control plane (multi-tenant por BD).
*/

/**
 * Registra, aprovisiona, activa e inicia sesión en un estudio; devuelve su slug y
 * un bearer token tenant-local.
 *
 * @return array{slug: string, bearer: string}
 */
function estudioConSesion(string $slug, string $email): array
{
    $r = test()->postJson('/api/v1/registro', [
        'nombre' => 'Estudio '.$slug,
        'slug' => $slug,
        'contacto_nombre' => 'Dueño',
        'contacto_email' => $email,
        'acepta_terminos' => true,
    ])->assertCreated();

    $slug = (string) $r->json('data.estudio.slug');
    $token = (string) $r->json('data.activacion.token');

    test()->postJson("/api/v1/app/{$slug}/activar", [
        'email' => $email, 'token' => $token,
        'password' => 'secreto123', 'password_confirmation' => 'secreto123',
    ])->assertCreated();

    $bearer = (string) test()->postJson("/api/v1/app/{$slug}/login", [
        'email' => $email, 'password' => 'secreto123',
    ])->assertOk()->json('data.token');

    return ['slug' => $slug, 'bearer' => $bearer];
}

/**
 * Cabecera Authorization con un bearer tenant-local.
 *
 * @return array<string, string>
 */
function conBearer(string $bearer): array
{
    return ['Authorization' => "Bearer {$bearer}"];
}

/**
 * Invita, activa e inicia sesión como personal con un rol; devuelve el bearer.
 */
function personalConSesion(string $slug, string $ownerBearer, string $email, string $rol): string
{
    $inv = test()->postJson("/api/v1/app/{$slug}/usuarios/invitar", [
        'nombre' => 'Personal', 'email' => $email, 'rol' => $rol,
    ], conBearer($ownerBearer))->assertCreated()->json('data.activacion');

    test()->postJson("/api/v1/app/{$slug}/activar", [
        'email' => $inv['email'], 'token' => $inv['token'],
        'password' => 'secreto123', 'password_confirmation' => 'secreto123',
    ])->assertCreated();

    return (string) test()->postJson("/api/v1/app/{$slug}/login", [
        'email' => $email, 'password' => 'secreto123',
    ])->assertOk()->json('data.token');
}
