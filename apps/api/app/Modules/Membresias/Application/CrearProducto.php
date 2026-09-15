<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Application;

use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Membresias\TipoProducto;

class CrearProducto
{
    public function ejecutar(
        string $nombre,
        TipoProducto $tipo,
        int $precioMinor,
        string $moneda,
        bool $ilimitado,
        ?int $creditosIncluidos,
        PoliticaReset $politicaReset = PoliticaReset::Ninguno,
        ?int $unidadesPorCiclo = null,
        PoliticaRollover $politicaRollover = PoliticaRollover::Ninguno,
        ?int $rolloverMax = null,
        ?int $actividadId = null,
        ?int $sucursalId = null,
    ): ProductoComercial {
        $recurrente = $politicaReset !== PoliticaReset::Ninguno;

        return ProductoComercial::create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'precio_minor' => $precioMinor,
            'moneda' => $moneda,
            'ilimitado' => $ilimitado,
            'creditos_incluidos' => $ilimitado ? null : $creditosIncluidos,
            'politica_reset' => $politicaReset,
            'unidades_por_ciclo' => ($ilimitado || ! $recurrente) ? null : $unidadesPorCiclo,
            'politica_rollover' => $politicaRollover,
            'rollover_max' => $politicaRollover === PoliticaRollover::Limitado ? $rolloverMax : null,
            'actividad_id' => $actividadId,
            'sucursal_id' => $sucursalId,
        ]);
    }
}
