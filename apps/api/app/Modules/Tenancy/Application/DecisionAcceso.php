<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

/**
 * Decision del motor de acceso (R12): si se permite la entrada, el codigo de razon
 * estable (ACCESS_BY_BOOKING / ACCESS_OPEN / NO_ACCESS) y, si aplica, la sesion que
 * la justifico.
 */
final readonly class DecisionAcceso
{
    public function __construct(
        public bool $permitido,
        public string $codigo,
        public ?int $sesionId = null,
    ) {}

    public static function permitir(string $codigo, ?int $sesionId = null): self
    {
        return new self(true, $codigo, $sesionId);
    }

    public static function denegar(string $codigo): self
    {
        return new self(false, $codigo);
    }
}
