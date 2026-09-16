<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Application\PoliticaAlumnosActivos;
use App\Modules\Tenancy\Application\PoliticaAlumnosActivosV1;
use App\Modules\Tenancy\Application\VerificadorGoogle;
use App\Modules\Tenancy\Application\VerificadorGoogleTokeninfo;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request/job lifecycle.
        $this->app->scoped(TenantContext::class);

        // Gestor de conexión del data plane: un estado activo por request/job para
        // que no se filtre la conexión de un tenant a otro (control plane nuevo).
        $this->app->scoped(GestorDeConexionTenant::class);

        // Definición de "alumno activo" (versionada); intercambiable sin reescribir
        // mediciones históricas.
        $this->app->bind(PoliticaAlumnosActivos::class, PoliticaAlumnosActivosV1::class);

        // Verificador de ID token de Google (SSO tenant-local); intercambiable en
        // pruebas por un doble que devuelve una identidad conocida.
        $this->app->bind(VerificadorGoogle::class, VerificadorGoogleTokeninfo::class);
    }

    public function boot(): void
    {
        // Every dispatched job captures the active tenant id in its payload...
        Queue::createPayloadUsing(function (): array {
            $tenantId = $this->app->make(TenantContext::class)->tenantId();

            return $tenantId === null ? [] : ['tenant_id' => $tenantId];
        });

        // ...and restores the tenant context (and spatie team scope) while it runs.
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $tenantId = $event->job->payload()['tenant_id'] ?? null;

            if (! is_int($tenantId)) {
                return;
            }

            $tenant = Tenant::query()->find($tenantId);

            if ($tenant instanceof Tenant) {
                $this->app->make(TenantContext::class)->set($tenant);
                $this->app->make(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            }
        });

        Event::listen([JobProcessed::class, JobFailed::class], function (): void {
            $this->app->make(TenantContext::class)->clear();
        });
    }
}
