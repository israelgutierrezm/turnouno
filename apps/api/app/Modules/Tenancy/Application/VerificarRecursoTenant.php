<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\EstadoSesionTenant;
use App\Modules\Tenancy\Exceptions\RecursoNoDisponible;
use App\Modules\Tenancy\Models\RecursoTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use Carbon\CarbonInterface;

/**
 * Motor de disponibilidad de recursos (R3): impide sobre-reservar un recurso. Cuenta
 * las sesiones PROGRAMADAS que solapan el intervalo y usan el mismo recurso; si ya se
 * alcanzo su cupo simultaneo (UNIDAD=1, POOL=capacidad) el recurso no esta disponible.
 * Opera sobre la BD del tenant resuelto.
 */
class VerificarRecursoTenant
{
    public function disponible(RecursoTenant $recurso, CarbonInterface $inicia, CarbonInterface $termina, ?int $excluirSerieId = null): bool
    {
        $solapadas = SesionTenant::query()
            ->where('recurso_id', $recurso->getKey())
            ->where('estado', EstadoSesionTenant::Programada->value)
            ->where('inicia_en', '<', $termina)
            ->where('termina_en', '>', $inicia)
            ->when($excluirSerieId !== null, fn ($q) => $q->where('serie_id', '!=', $excluirSerieId))
            ->count();

        return $solapadas < $recurso->cupoSimultaneo();
    }

    public function exigirDisponible(RecursoTenant $recurso, CarbonInterface $inicia, CarbonInterface $termina, ?int $excluirSerieId = null): void
    {
        if (! $this->disponible($recurso, $inicia, $termina, $excluirSerieId)) {
            throw new RecursoNoDisponible('El recurso ya esta ocupado en ese horario.');
        }
    }
}
