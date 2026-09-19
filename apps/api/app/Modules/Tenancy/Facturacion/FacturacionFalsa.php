<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Facturacion;

use Illuminate\Support\Str;

/**
 * Proveedor de facturación FALSO (dev/test y modo no-configurado): simula un timbre
 * determinista sin llamar a FacturAPI. Permite ejercitar todo el pipeline de
 * facturación sin llaves reales.
 */
class FacturacionFalsa implements ClienteFacturacion
{
    public function timbrar(string $llaveOrganizacion, array $factura): ResultadoTimbre
    {
        return new ResultadoTimbre('fake_'.Str::lower((string) Str::ulid()), (string) Str::uuid());
    }
}
