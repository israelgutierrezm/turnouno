<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Pagos\Application\ProcesarWebhookOpenPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook público de OpenPay. Verifica las credenciales HTTP Basic del webhook
 * (dentro del use case) antes de confiar en el evento. 400 si no valida.
 */
class WebhookOpenPayController
{
    public function __invoke(Request $request, ProcesarWebhookOpenPay $procesar): JsonResponse
    {
        $cuerpo = $request->getContent();
        $evento = json_decode($cuerpo, true);
        abort_unless(is_array($evento), 400);

        [$usuario, $password] = $this->credencialesBasic($request->header('Authorization'));

        $ok = $procesar->ejecutar($evento, $usuario, $password);
        abort_unless($ok, 400);

        return response()->json(['data' => ['recibido' => true]]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function credencialesBasic(?string $encabezado): array
    {
        if ($encabezado === null || ! str_starts_with($encabezado, 'Basic ')) {
            return [null, null];
        }

        $decodificado = base64_decode(substr($encabezado, 6), true);
        if ($decodificado === false || ! str_contains($decodificado, ':')) {
            return [null, null];
        }

        [$usuario, $password] = explode(':', $decodificado, 2);

        return [$usuario, $password];
    }
}
