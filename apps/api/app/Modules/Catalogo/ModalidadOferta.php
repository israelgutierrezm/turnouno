<?php

declare(strict_types=1);

namespace App\Modules\Catalogo;

enum ModalidadOferta: string
{
    case Grupal = 'grupal';
    case Privada = 'privada';
}
