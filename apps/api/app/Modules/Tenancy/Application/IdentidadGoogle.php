<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

/**
 * Identidad verificada devuelta por un {@see VerificadorGoogle}: los datos del
 * usuario que Google confirma tras validar el ID token.
 */
final readonly class IdentidadGoogle
{
    public function __construct(
        public string $googleId,
        public string $email,
        public bool $emailVerificado,
        public ?string $nombre,
    ) {}
}
