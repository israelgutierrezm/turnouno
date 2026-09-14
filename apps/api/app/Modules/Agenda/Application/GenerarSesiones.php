<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Application;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Models\PlantillaHorario;
use App\Modules\Agenda\Models\Sesion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Materializa las sesiones de una plantilla dentro de un rango de fechas locales
 * de la sucursal. Cada regla de recurrencia que caiga en el rango genera una
 * sesión concreta cuyos `inicia_en`/`termina_en` se guardan en UTC.
 *
 * Es idempotente: se apoya en `firstOrCreate` + el índice único
 * `(plantilla_horario_id, inicia_en)`, así que reejecutar el mismo rango no
 * duplica sesiones (ADR-0010). Devuelve cuántas sesiones se crearon.
 */
class GenerarSesiones
{
    public function ejecutar(PlantillaHorario $plantilla, string $desde, string $hasta): int
    {
        $zona = $plantilla->sucursal->zona_horaria ?? config('app.timezone');
        $zona = is_string($zona) ? $zona : 'UTC';

        $capacidad = $plantilla->capacidad ?? $plantilla->oferta->capacidad;

        // Ventana efectiva = intersección del rango pedido con la vigencia de la plantilla.
        $inicio = CarbonImmutable::parse($desde)->startOfDay();
        if ($inicio->lt($plantilla->vigente_desde)) {
            $inicio = CarbonImmutable::parse($plantilla->vigente_desde->toDateString())->startOfDay();
        }

        $fin = CarbonImmutable::parse($hasta)->startOfDay();
        if ($plantilla->vigente_hasta !== null) {
            $limite = CarbonImmutable::parse($plantilla->vigente_hasta->toDateString())->startOfDay();
            if ($fin->gt($limite)) {
                $fin = $limite;
            }
        }

        $reglas = $plantilla->reglas;

        return DB::transaction(function () use ($plantilla, $inicio, $fin, $reglas, $zona, $capacidad): int {
            $creadas = 0;

            for ($dia = $inicio; $dia->lte($fin); $dia = $dia->addDay()) {
                foreach ($reglas as $regla) {
                    if ($regla->dia_semana->value !== $dia->dayOfWeekIso) {
                        continue;
                    }

                    $iniciaEn = CarbonImmutable::parse($dia->toDateString().' '.$regla->hora_inicio, $zona)->utc();
                    $terminaEn = $iniciaEn->addMinutes($plantilla->duracion_minutos);

                    $sesion = Sesion::firstOrCreate(
                        [
                            'plantilla_horario_id' => $plantilla->id,
                            'inicia_en' => $iniciaEn,
                        ],
                        [
                            'oferta_id' => $plantilla->oferta_id,
                            'sucursal_id' => $plantilla->sucursal_id,
                            'recurso_id' => $plantilla->recurso_id,
                            'termina_en' => $terminaEn,
                            'zona_horaria' => $zona,
                            'capacidad' => $capacidad,
                            'estado' => EstadoSesion::Programada->value,
                        ],
                    );

                    if ($sesion->wasRecentlyCreated) {
                        $creadas++;
                    }
                }
            }

            return $creadas;
        });
    }
}
