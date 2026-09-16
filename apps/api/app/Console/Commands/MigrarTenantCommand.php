<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Application\MigradorLegacyATenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Migra tenants del esquema compartido legacy a su base por tenant
 * (expand-migrate-verify-cutover). Uso:
 *   php artisan datos:migrar-tenant --tenant=pole-house --dry-run
 *   php artisan datos:migrar-tenant --all
 */
class MigrarTenantCommand extends Command
{
    protected $signature = 'datos:migrar-tenant
        {--tenant= : Slug o id del tenant legacy a migrar}
        {--all : Migra todos los tenants legacy}
        {--dry-run : Solo muestra el plan (conteos), sin escribir}
        {--force : Re-migra aunque el estudio ya tenga datos}';

    protected $description = 'Migra un tenant legacy (esquema compartido) a su base por tenant.';

    public function handle(MigradorLegacyATenant $migrador): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->warn('No se encontraron tenants legacy para migrar.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        foreach ($tenants as $tenant) {
            $this->line("→ {$tenant->slug} ({$tenant->name})".($dryRun ? ' [dry-run]' : ''));
            $reporte = $migrador->migrar($tenant, $dryRun, $force);

            if (isset($reporte['omitido'])) {
                $this->warn("  omitido: {$reporte['omitido']}");

                continue;
            }

            /** @var array<string, int> $fuente */
            $fuente = $dryRun ? $reporte['plan'] : ($reporte['migrados'] ?? []);
            foreach ($fuente as $entidad => $cantidad) {
                $ok = $dryRun ? '' : (($reporte['verificacion'][$entidad] ?? true) ? ' ✓' : ' ✗');
                $this->line(sprintf('  %-14s %d%s', $entidad, $cantidad, $ok));
            }

            if (! $dryRun) {
                $this->info('  '.(($reporte['ok'] ?? false) ? 'Migracion verificada.' : 'Verificacion con diferencias.'));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('id')->get();
        }

        $ref = (string) $this->option('tenant');
        if ($ref === '') {
            $this->error('Indica --tenant=<slug|id> o --all.');

            /** @var Collection<int, Tenant> */
            return collect();
        }

        return Tenant::query()
            ->where('slug', $ref)
            ->orWhere('id', ctype_digit($ref) ? (int) $ref : 0)
            ->get();
    }
}
