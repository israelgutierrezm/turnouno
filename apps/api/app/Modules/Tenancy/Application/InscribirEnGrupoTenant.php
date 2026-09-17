<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\EstadoSesionTenant;
use App\Modules\Tenancy\Models\GrupoTenant;
use App\Modules\Tenancy\Models\InscripcionGrupoTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Inscribe a una persona en un grupo/curso (R25) y le AUTO-RESERVA las ocurrencias
 * futuras de la serie del grupo (reusa el motor de reserva: derecho, hold, capacidad;
 * si una sesion esta llena va a lista de espera). Una ocurrencia que no se puede
 * reservar (ya reservada, sin derecho…) se omite sin romper la inscripcion.
 */
class InscribirEnGrupoTenant
{
    public function __construct(private readonly ReservasTenant $reservas) {}

    /**
     * @return array{inscripcion: InscripcionGrupoTenant, reservadas: int}
     */
    public function inscribir(GrupoTenant $grupo, PersonaTenant $persona): array
    {
        $inscripcion = InscripcionGrupoTenant::query()->firstOrCreate(
            ['grupo_id' => $grupo->getKey(), 'persona_id' => $persona->getKey()],
            ['activo' => true],
        );

        $reservadas = 0;

        SesionTenant::query()
            ->where('serie_id', $grupo->plantilla_id)
            ->where('estado', EstadoSesionTenant::Programada->value)
            ->where('inicia_en', '>=', Carbon::now())
            ->orderBy('inicia_en')
            ->get()
            ->each(function (SesionTenant $sesion) use ($persona, &$reservadas): void {
                try {
                    $this->reservas->crear($sesion, $persona, null, true);
                    $reservadas++;
                } catch (Throwable) {
                    // Ya reservada / sin cupo / sin derecho: se omite esa ocurrencia.
                }
            });

        return ['inscripcion' => $inscripcion, 'reservadas' => $reservadas];
    }
}
