<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Exceptions\CheckinInvalido;
use App\Modules\Tenancy\Exceptions\IntegracionNoDisponible;
use App\Modules\Tenancy\Integraciones\RegistroDeIntegracionesTenant;
use App\Modules\Tenancy\Models\CheckinTenant;
use App\Modules\Tenancy\Models\SesionTenant;

/**
 * Registra el check-in de un usuario de una plataforma de bienestar (Wellhub /
 * TotalPass) en una sesion: valida el codigo contra la API del proveedor con las
 * credenciales del estudio y guarda el acceso. NO consume creditos del estudio (la
 * plataforma cubre la clase). Idempotente por `referencia_externa`: el mismo codigo
 * validado no se registra dos veces.
 */
class RegistrarCheckin
{
    public function __construct(private readonly RegistroDeIntegracionesTenant $registro) {}

    public function ejecutar(SesionTenant $sesion, string $proveedor, string $codigo): CheckinTenant
    {
        $validador = $this->registro->resolver($proveedor);

        if ($validador === null || ! $this->registro->activa($proveedor)) {
            throw new IntegracionNoDisponible('La integracion no esta activa en este estudio.');
        }

        $resultado = $validador->validar($codigo, $this->registro->credenciales($proveedor));

        if (! $resultado->valido) {
            throw new CheckinInvalido($resultado->motivo ?? 'Codigo de check-in no valido.');
        }

        // Idempotente: si ese codigo ya se valido, devuelve el check-in existente.
        $previo = CheckinTenant::query()->where('referencia_externa', $resultado->referencia)->first();
        if ($previo instanceof CheckinTenant) {
            return $previo;
        }

        return CheckinTenant::query()->create([
            'sesion_id' => $sesion->getKey(),
            'proveedor' => $proveedor,
            'referencia_externa' => $resultado->referencia,
            'nombre_usuario' => $resultado->usuario,
            'estado' => 'validado',
            'registrado_en' => now(),
        ]);
    }
}
