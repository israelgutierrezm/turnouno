<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Facturacion;

use Illuminate\Support\Facades\Http;

/**
 * Proveedor real de facturación: FacturAPI (v2). Timbra un CFDI con la llave de la
 * organización del tenant. La prueba en vivo queda pendiente de llaves reales
 * (igual que el cobro con pasarela); el pipeline se prueba con {@see FacturacionFalsa}.
 */
class FacturApiHttp implements ClienteFacturacion
{
    public function __construct(private readonly string $baseUrl) {}

    public function timbrar(string $llaveOrganizacion, array $factura): ResultadoTimbre
    {
        $respuesta = Http::withToken($llaveOrganizacion)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->post('/invoices', $factura);

        if ($respuesta->failed()) {
            throw new TimbradoFallido((string) ($respuesta->json('message') ?? 'FacturAPI rechazo el timbrado.'));
        }

        return new ResultadoTimbre(
            (string) $respuesta->json('id'),
            (string) $respuesta->json('uuid'),
        );
    }

    public function descargar(string $llaveOrganizacion, string $facturaId, string $formato): string
    {
        $respuesta = Http::withToken($llaveOrganizacion)
            ->baseUrl($this->baseUrl)
            ->get("/invoices/{$facturaId}/{$formato}");

        if ($respuesta->failed()) {
            throw new TimbradoFallido((string) ($respuesta->json('message') ?? 'FacturAPI no pudo entregar el archivo.'));
        }

        return $respuesta->body();
    }
}
