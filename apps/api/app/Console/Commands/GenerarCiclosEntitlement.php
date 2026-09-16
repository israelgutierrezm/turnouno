<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Membresias\Application\GenerarCicloEntitlement;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Tenancy\Application\GenerarCicloEntitlementTenant;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\EstadoEstudio;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Avanza los ciclos vencidos de todos los derechos recurrentes (todos los
 * tenants). Pensado para correr a diario por el scheduler.
 */
class GenerarCiclosEntitlement extends Command
{
    protected $signature = 'entitlements:generar-ciclos';

    protected $description = 'Reinicia/renueva los ciclos vencidos de los derechos recurrentes';

    public function handle(
        GenerarCicloEntitlement $generar,
        TenantContext $contexto,
        GenerarCicloEntitlementTenant $generarTenant,
        GestorDeConexionTenant $gestor,
    ): int {
        $avanzados = 0;

        /** @var array<int, Tenant|null> $tenants */
        $tenants = [];

        // Chunk por id para no cargar todos los derechos en memoria (F-13).
        Derecho::query()
            ->withoutGlobalScope('tenant')
            ->where('politica_reset', '!=', 'ninguno')
            ->whereNotNull('ciclo_fin')
            ->whereDate('ciclo_fin', '<', now()->toDateString())
            ->chunkById(200, function ($derechos) use (&$avanzados, &$tenants, $generar, $contexto): void {
                foreach ($derechos as $derecho) {
                    $tenant = $tenants[$derecho->tenant_id] ??= Tenant::query()->find($derecho->tenant_id);
                    if ($tenant === null) {
                        continue;
                    }

                    $contexto->set($tenant);
                    // GenerarCicloEntitlement omite acuerdos no activos (F-13).
                    $avanzados += $generar->ejecutar($derecho);
                    $contexto->clear();
                }
            });

        // Plano TENANT (BD por estudio): recorre cada estudio operativo y avanza los
        // ciclos de sus derechos recurrentes dentro de su propia base.
        $avanzadosTenant = 0;
        Estudio::query()
            ->whereIn('estado', [EstadoEstudio::Trialing->value, EstadoEstudio::Active->value])
            ->chunkById(100, function (Collection $estudios) use (&$avanzadosTenant, $generarTenant, $gestor): void {
                /** @var Collection<int, Estudio> $estudios */
                foreach ($estudios as $estudio) {
                    if (! $gestor->baseDeDatosExiste($estudio)) {
                        continue;
                    }

                    $avanzadosTenant += $gestor->ejecutarEn($estudio, function () use ($generarTenant): int {
                        $n = 0;
                        DerechoTenant::query()
                            ->where('politica_reset', '!=', 'ninguno')
                            ->whereNotNull('ciclo_fin')
                            ->whereDate('ciclo_fin', '<', now()->toDateString())
                            ->chunkById(200, function (Collection $derechos) use (&$n, $generarTenant): void {
                                /** @var Collection<int, DerechoTenant> $derechos */
                                foreach ($derechos as $derecho) {
                                    $n += $generarTenant->ejecutar($derecho);
                                }
                            });

                        return $n;
                    });
                }
            });

        $this->info("Ciclos avanzados — legacy: {$avanzados}, tenant: {$avanzadosTenant}.");

        return self::SUCCESS;
    }
}
