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
            // La pasarela simulada aprueba sin dinero: jamás debe existir en producción.
            if (! $this->simuladaPermitida()) {
                throw new RuntimeException("La pasarela 'simulada' no está permitida en este entorno.");
            }

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
     * Nombres de pasarela disponibles para el personal (staff) del tenant. Incluye
     * los integrados (manual; simulada solo fuera de producción) y los configurables
     * activos. NO usar para el canal del miembro (ver `publicasActivas`).
     *
     * @return list<string>
     */
    public function disponibles(): array
    {
        $integrados = $this->simuladaPermitida()
            ? ProveedorPasarela::integrados()
            : [ProveedorPasarela::Manual->value];

        return array_merge($integrados, $this->configurablesActivos());
    }

    /**
     * Pasarelas que el MIEMBRO puede usar en el portal: solo configurables activas
     * (en línea + ventanilla) del tenant. Excluye `manual`/`simulada`, que aprueban
     * sin dinero y solo corresponden al staff/entornos de prueba (F-01/SEC-01).
     *
     * @return list<string>
     */
    public function publicasActivas(): array
    {
        return $this->configurablesActivos();
    }

    /**
     * Configurables (en línea + ventanilla) que el tenant tiene activas.
     *
     * @return list<string>
     */
    private function configurablesActivos(): array
    {
        /** @var list<string> $activas */
        $activas = ConfiguracionPasarela::query()
            ->where('activa', true)
            ->whereIn('proveedor', $this->configurables())
            ->pluck('proveedor')
            ->all();

        return $activas;
    }

    /**
     * @return list<string>
     */
    private function configurables(): array
    {
        return array_merge(ProveedorPasarela::enLinea(), [ProveedorPasarela::Ventanilla->value]);
    }

    private function simuladaPermitida(): bool
    {
        return ! app()->environment('production');
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
