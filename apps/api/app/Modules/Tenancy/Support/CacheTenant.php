<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache con aislamiento por estudio: prefija cada clave con el estudio activo
 * (`estudio:{id}:`), de modo que dos estudios nunca comparten ni pisan una entrada.
 * Sin estudio activo usa el prefijo `global:` (control plane). Usa el store de cache
 * configurado por debajo.
 */
class CacheTenant
{
    public function __construct(private readonly GestorDeConexionTenant $gestor) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function recordar(string $clave, int $segundos, Closure $callback): mixed
    {
        return Cache::remember($this->clave($clave), $segundos, $callback);
    }

    public function poner(string $clave, mixed $valor, int $segundos): void
    {
        Cache::put($this->clave($clave), $valor, $segundos);
    }

    public function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        return Cache::get($this->clave($clave), $porDefecto);
    }

    public function olvidar(string $clave): void
    {
        Cache::forget($this->clave($clave));
    }

    /**
     * Clave namespaced por el estudio activo.
     */
    public function clave(string $clave): string
    {
        $estudio = $this->gestor->actual();
        $ambito = $estudio !== null ? (string) $estudio->id : 'global';

        return "estudio:{$ambito}:{$clave}";
    }
}
