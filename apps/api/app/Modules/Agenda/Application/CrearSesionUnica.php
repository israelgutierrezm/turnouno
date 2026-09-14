<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Application;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Models\Recurso;
use Carbon\CarbonImmutable;

/**
 * Crea una sesión única (ad-hoc), sin plantilla: útil para clases privadas o
 * eventos puntuales. La hora se recibe en zona local de la sucursal y se guarda
 * en UTC, igual que las sesiones materializadas.
 */
class CrearSesionUnica
{
    /**
     * @param  array{inicia_en_local: string, duracion_minutos: int, capacidad?: int|null}  $datos
     */
    public function ejecutar(Oferta $oferta, Sucursal $sucursal, array $datos, ?Recurso $recurso = null): Sesion
    {
        $zona = $sucursal->zona_horaria ?? config('app.timezone');
        $zona = is_string($zona) ? $zona : 'UTC';

        $iniciaEn = CarbonImmutable::parse($datos['inicia_en_local'], $zona)->utc();
        $terminaEn = $iniciaEn->addMinutes($datos['duracion_minutos']);

        return Sesion::create([
            'plantilla_horario_id' => null,
            'oferta_id' => $oferta->id,
            'sucursal_id' => $sucursal->id,
            'recurso_id' => $recurso?->id,
            'inicia_en' => $iniciaEn,
            'termina_en' => $terminaEn,
            'zona_horaria' => $zona,
            'capacidad' => $datos['capacidad'] ?? $oferta->capacidad,
            'estado' => EstadoSesion::Programada->value,
        ]);
    }
}
