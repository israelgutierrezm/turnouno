<?php

declare(strict_types=1);

namespace App\Modules\Membresias;

enum EstadoAcuerdo: string
{
    case Activo = 'activo';
    case Pausado = 'pausado';
    case Cancelado = 'cancelado';
}
