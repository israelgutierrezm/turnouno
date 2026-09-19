<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Facturacion;

use RuntimeException;

/**
 * El proveedor rechazó el timbrado del CFDI (datos inválidos, RFC inexistente, etc.).
 */
class TimbradoFallido extends RuntimeException {}
