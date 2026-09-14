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

        return $this->crearEnLinea($nombre, $config);
    }

    /**
     * Nombres de pasarela disponibles para cobrar en el tenant actual.
     *
     * @return list<string>
     */
    public function disponibles(): array
    {
        $enLinea = ConfiguracionPasarela::query()
            ->where('activa', true)
            ->whereIn('proveedor', ProveedorPasarela::enLinea())
            ->pluck('proveedor')
            ->all();

        /** @var list<string> $enLinea */
        return array_merge(ProveedorPasarela::integrados(), $enLinea);
    }

    private function crearEnLinea(string $nombre, ConfiguracionPasarela $config): PasarelaEnLinea
    {
        return match ($nombre) {
            ProveedorPasarela::Stripe->value => new PasarelaStripe($config),
            ProveedorPasarela::OpenPay->value => new PasarelaOpenPay($config),
            ProveedorPasarela::MercadoPago->value => new PasarelaMercadoPago($config),
            default => throw new RuntimeException("Pasarela en línea desconocida: {$nombre}."),
        };
    }
}
