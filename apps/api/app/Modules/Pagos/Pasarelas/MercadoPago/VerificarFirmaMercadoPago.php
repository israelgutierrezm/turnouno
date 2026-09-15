<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas\MercadoPago;

/**
 * Verifica la firma de un webhook de Mercado Pago (`x-signature`): sobre el
 * manifiesto `id:{data.id};request-id:{x-request-id};ts:{ts};` con HMAC-SHA256 y
 * el secreto del webhook, comparado con `v1`.
 */
class VerificarFirmaMercadoPago
{
    public static function valida(string $dataId, string $requestId, ?string $xSignature, string $secreto): bool
    {
        if ($xSignature === null || $xSignature === '') {
            return false;
        }

        $partes = [];
        foreach (explode(',', $xSignature) as $segmento) {
            [$clave, $valor] = array_pad(explode('=', trim($segmento), 2), 2, '');
            $partes[$clave] = $valor;
        }

        $marca = $partes['ts'] ?? '';
        $firma = $partes['v1'] ?? '';
        if ($marca === '' || $firma === '') {
            return false;
        }

        $manifiesto = "id:{$dataId};request-id:{$requestId};ts:{$marca};";
        $esperada = hash_hmac('sha256', $manifiesto, $secreto);

        return hash_equals($esperada, $firma);
    }
}
