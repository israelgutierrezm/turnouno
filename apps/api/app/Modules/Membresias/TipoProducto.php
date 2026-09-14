<?php

declare(strict_types=1);

namespace App\Modules\Membresias;

enum TipoProducto: string
{
    case Membresia = 'membresia';
    case Paquete = 'paquete';
    case PaseDia = 'pase_dia';
    case SesionIndividual = 'sesion_individual';
    case AddOn = 'add_on';
    case Taller = 'taller';
}
