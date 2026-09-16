<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Integraciones;

/**
 * Valida el codigo de check-in de un usuario de una plataforma de bienestar
 * (Wellhub / TotalPass) contra la API del proveedor, usando las credenciales del
 * propio estudio.
 */
interface ValidadorPartner
{
    public function nombre(): string;

    /**
     * @param  array<string, string>  $credenciales  del estudio (descifradas)
     */
    public function validar(string $codigo, array $credenciales): ResultadoCheckin;
}
