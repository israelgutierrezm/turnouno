<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Membresias\Application\GenerarCicloEntitlement;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Avanza los ciclos vencidos de todos los derechos recurrentes (todos los
 * tenants). Pensado para correr a diario por el scheduler.
 */
class GenerarCiclosEntitlement extends Command
{
    protected $signature = 'entitlements:generar-ciclos';

    protected $description = 'Reinicia/renueva los ciclos vencidos de los derechos recurrentes';

    public function handle(GenerarCicloEntitlement $generar, TenantContext $contexto): int
    {
        $derechos = Derecho::query()
            ->withoutGlobalScope('tenant')
            ->where('politica_reset', '!=', 'ninguno')
            ->whereNotNull('ciclo_fin')
            ->whereDate('ciclo_fin', '<', now()->toDateString())
            ->get();

        $avanzados = 0;
        foreach ($derechos as $derecho) {
            $tenant = Tenant::query()->find($derecho->tenant_id);
            if ($tenant === null) {
                continue;
            }

            $contexto->set($tenant);
            $avanzados += $generar->ejecutar($derecho);
            $contexto->clear();
        }

        $this->info("Ciclos avanzados: {$avanzados}.");

        return self::SUCCESS;
    }
}
