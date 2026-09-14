<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Application;

use App\Modules\Membresias\Models\ProductoComercial;
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
    ): ProductoComercial {
        return ProductoComercial::create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'precio_minor' => $precioMinor,
            'moneda' => $moneda,
            'ilimitado' => $ilimitado,
            'creditos_incluidos' => $ilimitado ? null : $creditosIncluidos,
        ]);
    }
}
