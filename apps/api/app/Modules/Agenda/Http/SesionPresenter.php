<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http;

use App\Modules\Agenda\Models\AsignacionSesion;
use App\Modules\Agenda\Models\Sesion;

/**
 * Da forma a la representación JSON de una sesión. Las horas se exponen en ISO-8601
 * UTC más `zona_horaria`, para que el cliente muestre la hora local de la sucursal.
 */
class SesionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(Sesion $sesion): array
    {
        $sesion->loadMissing(['oferta', 'asignaciones.persona']);

        return [
            'id' => $sesion->ulid,
            'oferta' => $sesion->oferta->nombre,
            'inicia_en' => $sesion->inicia_en->toIso8601String(),
            'termina_en' => $sesion->termina_en->toIso8601String(),
            'zona_horaria' => $sesion->zona_horaria,
            'capacidad' => $sesion->capacidad,
            'estado' => $sesion->estado->value,
            'instructores' => $sesion->asignaciones
                ->map(static fn (AsignacionSesion $asignacion): array => [
                    'persona_id' => $asignacion->persona->ulid,
                    'nombre' => trim($asignacion->persona->nombre.' '.($asignacion->persona->apellidos ?? '')),
                    'rol' => $asignacion->rol->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
