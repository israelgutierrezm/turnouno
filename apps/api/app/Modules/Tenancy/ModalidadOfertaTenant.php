<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Modalidad de una oferta del catálogo tenant-local.
 */
enum ModalidadOfertaTenant: string
{
    case Grupal = 'grupal';
    case Privada = 'privada';
    case Individual = 'individual';
}
