<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Integraciones;

/**
 * Resultado de validar un codigo de check-in contra una plataforma de bienestar.
 */
final readonly class ResultadoCheckin
{
    public function __construct(
        public bool $valido,
        public string $referencia,
        public ?string $usuario = null,
        public ?string $motivo = null,
    ) {}

    public static function valido(string $referencia, ?string $usuario = null): self
    {
        return new self(true, $referencia, $usuario);
    }

    public static function invalido(string $motivo): self
    {
        return new self(false, '', null, $motivo);
    }
}
