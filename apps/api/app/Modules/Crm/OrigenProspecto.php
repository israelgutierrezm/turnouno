<?php

declare(strict_types=1);

namespace App\Modules\Crm;

/**
 * Canal por el que llegó el prospecto (lead), para segmentar y medir conversión
 * por fuente (R15).
 */
enum OrigenProspecto: string
{
    case Web = 'web';
    case Referido = 'referido';
    case Marketplace = 'marketplace';
    case Presencial = 'presencial';
    case Redes = 'redes';
    case Otro = 'otro';
}
