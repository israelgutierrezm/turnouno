<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

/**
 * Resultado de un intento de cobro devuelto por una pasarela.
 */
final readonly class ResultadoPago
{
    public function __construct(
        public EstadoResultado $estado,
        public ?string $referencia = null,
        public ?string $motivo = null,
    ) {}

    public static function aprobado(string $referencia): self
    {
        return new self(EstadoResultado::Aprobado, $referencia);
    }

    public static function rechazado(string $motivo): self
    {
        return new self(EstadoResultado::Rechazado, null, $motivo);
    }

    public static function pendiente(string $referencia): self
    {
        return new self(EstadoResultado::Pendiente, $referencia);
    }

    public function esAprobado(): bool
    {
        return $this->estado === EstadoResultado::Aprobado;
    }

    public function esPendiente(): bool
    {
        return $this->estado === EstadoResultado::Pendiente;
    }
}
