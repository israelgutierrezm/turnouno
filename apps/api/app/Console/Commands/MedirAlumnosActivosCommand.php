<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Application\MedirAlumnosActivos;
use App\Modules\Tenancy\EstadoEstudio;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Mide los alumnos activos de cada estudio operativo y guarda el agregado en el
 * control plane. `--congelar` cierra el periodo (fija la medición para facturar).
 * Idempotente y por lotes (chunkById).
 */
class MedirAlumnosActivosCommand extends Command
{
    protected $signature = 'facturacion:medir {--periodo=} {--congelar}';

    protected $description = 'Mide los alumnos activos de cada estudio (agregado al control plane)';

    public function handle(MedirAlumnosActivos $medir): int
    {
        $periodo = is_string($this->option('periodo')) && $this->option('periodo') !== ''
            ? (string) $this->option('periodo')
            : now()->format('Y-m');
        $congelar = (bool) $this->option('congelar');

        Estudio::query()
            ->whereIn('estado', [EstadoEstudio::Trialing->value, EstadoEstudio::Active->value])
            ->chunkById(100, function (Collection $estudios) use ($medir, $periodo, $congelar): void {
                /** @var Collection<int, Estudio> $estudios */
                foreach ($estudios as $estudio) {
                    $medicion = $congelar
                        ? $medir->congelar($estudio, $periodo)
                        : $medir->ejecutar($estudio, $periodo);

                    $this->line("{$estudio->slug} [{$periodo}]: {$medicion->cantidad} alumnos activos");
                }
            });

        return self::SUCCESS;
    }
}
