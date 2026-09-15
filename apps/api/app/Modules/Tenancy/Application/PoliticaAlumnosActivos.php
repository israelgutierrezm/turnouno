<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

/**
 * Definición (versionada) de "alumno activo" para la facturación SaaS. La regla
 * cuenta PERSONAS distintas (no eventos) dentro de la BD del tenant y puede
 * cambiar en el futuro publicando una nueva versión, SIN reescribir las mediciones
 * ya congeladas (cada medición guarda la versión con que se calculó).
 */
interface PoliticaAlumnosActivos
{
    /**
     * Identificador de versión de la regla (se guarda con cada medición).
     */
    public function version(): string;

    /**
     * Cuenta las personas distintas que califican como alumno activo en el periodo
     * (YYYY-MM). Se ejecuta con la conexión `tenant` ya activa.
     */
    public function contar(string $periodo): int;

    /**
     * Evidencia verificable de cómo se calculó (regla, filtros, periodo).
     *
     * @return array<string, mixed>
     */
    public function evidencia(string $periodo): array;
}
