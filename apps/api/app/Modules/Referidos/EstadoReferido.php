<?php

declare(strict_types=1);

namespace App\Modules\Referidos;

/**
 * Estado de un referido (R23): pendiente hasta que se convierte en miembro; al
 * convertirse se genera la recompensa (cupón) para quien refirió.
 */
enum EstadoReferido: string
{
    case Pendiente = 'pendiente';
    case Convertido = 'convertido';
}
