<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use RuntimeException;

/**
 * Resuelve una pasarela por su nombre. Registrar una pasarela nueva (real) es
 * agregarla aquí sin tocar el dominio del cobro.
 */
class RegistroDePasarelas
{
    /**
     * @var array<string, PasarelaDePago>
     */
    private array $mapa;

    public function __construct(PasarelaManual $manual, PasarelaSimulada $simulada)
    {
        $this->mapa = [
            $manual->nombre() => $manual,
            $simulada->nombre() => $simulada,
        ];
    }

    public function para(string $nombre): PasarelaDePago
    {
        return $this->mapa[$nombre] ?? throw new RuntimeException("Pasarela de pago desconocida: {$nombre}.");
    }

    /**
     * @return list<string>
     */
    public function disponibles(): array
    {
        return array_keys($this->mapa);
    }
}
