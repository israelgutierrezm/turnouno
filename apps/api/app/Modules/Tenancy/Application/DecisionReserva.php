<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

/**
 * Resultado ESTRUCTURADO de evaluar una reserva (ver docs/BOOKING_ENGINE.md,
 * seccion Explainability). El motor devuelve una decision explicable en lugar de
 * depender de excepciones para las decisiones normales de negocio: `permitida` +
 * `codigo` estable + las reglas evaluadas + el costo en creditos + el derecho a
 * usar + advertencias (p. ej. que la reserva ira a lista de espera).
 */
final readonly class DecisionReserva
{
    /**
     * @param  array<string, bool>  $reglas  regla evaluada => paso (true) / fallo (false)
     * @param  list<string>  $advertencias
     */
    public function __construct(
        public bool $permitida,
        public ?string $codigo,
        public ?string $mensaje,
        public array $reglas,
        public int $costoCreditos,
        public ?string $derecho,
        public array $advertencias,
    ) {}

    /**
     * @param  array<string, bool>  $reglas
     * @param  list<string>  $advertencias
     */
    public static function permitir(array $reglas, int $costoCreditos, ?string $derecho, array $advertencias = []): self
    {
        return new self(true, null, null, $reglas, $costoCreditos, $derecho, $advertencias);
    }

    /**
     * @param  array<string, bool>  $reglas
     */
    public static function rechazar(string $codigo, string $mensaje, array $reglas): self
    {
        return new self(false, $codigo, $mensaje, $reglas, 0, null, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'permitida' => $this->permitida,
            'reason_code' => $this->codigo,
            'mensaje' => $this->mensaje,
            'reglas_evaluadas' => $this->reglas,
            'costo_creditos' => $this->costoCreditos,
            'derecho' => $this->derecho,
            'advertencias' => $this->advertencias,
        ];
    }
}
