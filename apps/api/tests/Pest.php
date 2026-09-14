<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Membresias\TipoProducto;
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
