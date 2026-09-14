<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Application\CrearTenant;
use App\Modules\Tenancy\Application\VincularUsuarioATenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
