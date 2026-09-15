<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Tipo de un campo de formulario dinámico.
 */
enum TipoCampo: string
{
    case Texto = 'texto';
    case Textarea = 'textarea';
    case Numero = 'numero';
    case Fecha = 'fecha';
    case Booleano = 'booleano';
    case Seleccion = 'seleccion';
}
