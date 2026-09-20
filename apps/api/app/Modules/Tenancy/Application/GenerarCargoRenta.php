<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\EstadoCargoRenta;
use App\Modules\Tenancy\Models\CargoRenta;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Support\Carbon;

/**
 * Genera (o actualiza) el cargo de renta del SaaS de un estudio para un periodo. Mide
 * los alumnos activos en la BD del tenant y calcula el monto según el modo de cobro
 * (activos × precio, o cuota fija). Idempotente por (estudio, periodo): no re-genera ni
 * pisa un cargo ya pagado.
 */
class GenerarCargoRenta
{
    public function __construct(
        private readonly GestorDeConexionTenant $gestor,
        private readonly PoliticaAlumnosActivos $politica,
    ) {}

    public function paraEstudio(Estudio $estudio, string $periodo): CargoRenta
    {
        // El conteo vive en la BD del tenant; el cargo, en el control plane.
        $activos = (int) $this->gestor->ejecutarEn($estudio, fn (): int => $this->politica->contar($periodo));
        $monto = $estudio->cargoDelPeriodo($activos);

        $existente = CargoRenta::query()
            ->where('estudio_id', $estudio->getKey())
            ->where('periodo', $periodo)
            ->first();

        // Un cargo ya pagado no se re-genera.
        if ($existente !== null && $existente->estado === EstadoCargoRenta::Pagado) {
            return $existente;
        }

        return CargoRenta::query()->updateOrCreate(
            ['estudio_id' => $estudio->getKey(), 'periodo' => $periodo],
            [
                'modo_cobro' => $estudio->modo_cobro->value,
                'alumnos_activos' => $activos,
                'monto_minor' => $monto,
                'moneda' => $estudio->moneda,
                'estado' => EstadoCargoRenta::Pendiente->value,
                'vence_en' => Carbon::createFromFormat('Y-m', $periodo)->endOfMonth()->addDays(10)->toDateString(),
            ],
        );
    }
}
