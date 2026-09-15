<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\Models\MedicionUso;

/**
 * Mide los alumnos activos de un estudio en un periodo (YYYY-MM). Cuenta DENTRO de
 * la BD del tenant y envía al control plane SOLO el agregado (cantidad) + la regla
 * y su evidencia. Idempotente: recalcula mientras el periodo esté abierto y no toca
 * una medición congelada (base para no reescribir facturas ya emitidas).
 */
class MedirAlumnosActivos
{
    public function __construct(
        private readonly GestorDeConexionTenant $gestor,
        private readonly PoliticaAlumnosActivos $politica,
    ) {}

    public function ejecutar(Estudio $estudio, string $periodo): MedicionUso
    {
        $existente = MedicionUso::query()
            ->where('estudio_id', $estudio->id)
            ->where('periodo', $periodo)
            ->first();

        // Periodo congelado: no se recalcula (la factura ya está fijada).
        if ($existente instanceof MedicionUso && $existente->congelada) {
            return $existente;
        }

        // El conteo ocurre en la BD del tenant; solo el agregado sale al control plane.
        $cantidad = $this->gestor->ejecutarEn($estudio, fn (): int => $this->politica->contar($periodo));

        return MedicionUso::query()->updateOrCreate(
            ['estudio_id' => $estudio->id, 'periodo' => $periodo],
            [
                'regla_version' => $this->politica->version(),
                'cantidad' => $cantidad,
                'evidencia' => $this->politica->evidencia($periodo),
                'calculada_en' => now(),
            ],
        );
    }

    /**
     * Congela el periodo (cierre): fija la medición para facturación.
     */
    public function congelar(Estudio $estudio, string $periodo): MedicionUso
    {
        $medicion = $this->ejecutar($estudio, $periodo);
        $medicion->forceFill(['congelada' => true])->save();

        return $medicion;
    }
}
