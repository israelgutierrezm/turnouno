<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

/**
 * Resultado de un intento de cobro devuelto por una pasarela.
 */
final readonly class ResultadoPago
{
    public function __construct(
        public bool $aprobado,
        public ?string $referencia = null,
        public ?string $motivo = null,
    ) {}

    public static function aprobado(string $referencia): self
    {
        return new self(true, $referencia);
    }

    public static function rechazado(string $motivo): self
    {
        return new self(false, null, $motivo);
    }
}
