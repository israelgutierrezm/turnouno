<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\ProveedorPasarela;
use RuntimeException;

/**
 * Resuelve pasarelas para el tenant actual. Integrados (manual, simulada) siempre
 * disponibles; las de en línea solo si el tenant las tiene activas y configuradas
 * (on/off por tenant). Las consultas de configuración son tenant-scoped.
 */
class RegistroDePasarelas
{
    public function __construct(
        private readonly PasarelaManual $manual,
        private readonly PasarelaSimulada $simulada,
    ) {}

    public function para(string $nombre): PasarelaDePago
    {
        if ($nombre === ProveedorPasarela::Manual->value) {
            return $this->manual;
        }

        if ($nombre === ProveedorPasarela::Simulada->value) {
            return $this->simulada;
        }

        $config = ConfiguracionPasarela::query()
            ->where('proveedor', $nombre)
            ->where('activa', true)
            ->first();

        if ($config === null) {
            throw new RuntimeException("La pasarela '{$nombre}' no está activa para este tenant.");
        }

        return $this->crearConfigurable($nombre, $config);
    }

    /**
     * Nombres de pasarela disponibles para cobrar en el tenant actual.
     *
     * @return list<string>
     */
    public function disponibles(): array
    {
        $configuradas = ConfiguracionPasarela::query()
            ->where('activa', true)
            ->whereIn('proveedor', $this->configurables())
            ->pluck('proveedor')
            ->all();

        /** @var list<string> $configuradas */
        return array_merge(ProveedorPasarela::integrados(), $configuradas);
    }

    /**
     * @return list<string>
     */
    private function configurables(): array
    {
        return array_merge(ProveedorPasarela::enLinea(), [ProveedorPasarela::Ventanilla->value]);
    }

    private function crearConfigurable(string $nombre, ConfiguracionPasarela $config): PasarelaDePago
    {
        return match ($nombre) {
            ProveedorPasarela::Stripe->value => new PasarelaStripe($config),
            ProveedorPasarela::OpenPay->value => new PasarelaOpenPay($config),
            ProveedorPasarela::MercadoPago->value => new PasarelaMercadoPago($config),
            ProveedorPasarela::Ventanilla->value => new PasarelaVentanilla,
            default => throw new RuntimeException("Pasarela configurable desconocida: {$nombre}."),
        };
    }
}
