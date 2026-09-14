<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;

/**
 * Abstracción proveedor-agnóstica de una pasarela de pago. El dominio no conoce
 * proveedores concretos: cobra a través de esta interfaz (ver PRODUCT.md,
 * "provider-independent payments").
 */
interface PasarelaDePago
{
    public function nombre(): string;

    public function cobrar(Pago $pago): ResultadoPago;
}
