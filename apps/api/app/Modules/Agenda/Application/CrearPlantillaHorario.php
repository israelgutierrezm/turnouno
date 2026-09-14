<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Application;

use App\Modules\Agenda\Models\PlantillaHorario;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Models\Recurso;
use Illuminate\Support\Facades\DB;

/**
 * Crea una plantilla de horario y sus reglas de recurrencia en una sola
 * transacción. La materialización de sesiones se hace después con
 * {@see GenerarSesiones}.
 */
class CrearPlantillaHorario
{
    /**
     * @param  array{duracion_minutos: int, vigente_desde: string, vigente_hasta?: string|null, capacidad?: int|null, nombre?: string|null}  $datos
     * @param  list<array{dia_semana: int, hora_inicio: string}>  $reglas
     */
    public function ejecutar(Oferta $oferta, Sucursal $sucursal, array $datos, array $reglas, ?Recurso $recurso = null): PlantillaHorario
    {
        return DB::transaction(function () use ($oferta, $sucursal, $datos, $reglas, $recurso): PlantillaHorario {
            $plantilla = PlantillaHorario::create([
                'oferta_id' => $oferta->id,
                'sucursal_id' => $sucursal->id,
                'recurso_id' => $recurso?->id,
                'nombre' => $datos['nombre'] ?? null,
                'duracion_minutos' => $datos['duracion_minutos'],
                'capacidad' => $datos['capacidad'] ?? null,
                'vigente_desde' => $datos['vigente_desde'],
                'vigente_hasta' => $datos['vigente_hasta'] ?? null,
                'activa' => true,
            ]);

            foreach ($reglas as $regla) {
                $plantilla->reglas()->create([
                    'dia_semana' => $regla['dia_semana'],
                    'hora_inicio' => $regla['hora_inicio'],
                ]);
            }

            return $plantilla;
        });
    }
}
