<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Facturacion;

/**
 * Contrato del proveedor de facturación (CFDI). Abstrae FacturAPI para que el motor
 * de facturación sea testeable (con {@see FacturacionFalsa}) y el proveedor real
 * ({@see FacturApiHttp}) se pruebe en vivo cuando haya llaves.
 */
interface ClienteFacturacion
{
    /**
     * Timbra un CFDI con la llave de la organización dada y devuelve su id + UUID.
     *
     * @param  array<string, mixed>  $factura  cuerpo del CFDI (receptor, conceptos, uso...)
     *
     * @throws TimbradoFallido
     */
    public function timbrar(string $llaveOrganizacion, array $factura): ResultadoTimbre;
}
