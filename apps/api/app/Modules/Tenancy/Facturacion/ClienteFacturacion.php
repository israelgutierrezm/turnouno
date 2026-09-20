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

    /**
     * Descarga el archivo de un CFDI ya timbrado (contenido binario del PDF o XML).
     *
     * @param  string  $formato  'pdf' o 'xml'
     *
     * @throws TimbradoFallido
     */
    public function descargar(string $llaveOrganizacion, string $facturaId, string $formato): string;
}
