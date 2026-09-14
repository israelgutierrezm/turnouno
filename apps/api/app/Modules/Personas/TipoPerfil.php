<?php

declare(strict_types=1);

namespace App\Modules\Personas;

/**
 * Los roles que una Persona puede ostentar dentro de un tenant. Una persona
 * puede tener varios simultáneamente (ver docs/DOMAIN_MODEL.md).
 */
enum TipoPerfil: string
{
    case Miembro = 'miembro';
    case Tutor = 'tutor';
    case Instructor = 'instructor';
    case Personal = 'personal';
    case Lead = 'lead';
    case Cliente = 'cliente';
}
