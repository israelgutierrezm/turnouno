<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Integraciones;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Base HTTP para validar check-ins contra una plataforma de bienestar. Con la
 * `api_key` del estudio hace POST {base_url}/check-ins {code} (Bearer). Testeable con
 * Http::fake. El endpoint/campos exactos se ajustan por proveedor cuando el estudio
 * tenga credenciales; la estructura queda lista.
 */
abstract class ValidadorPartnerHttp implements ValidadorPartner
{
    abstract protected function baseUrlPorDefecto(): string;

    public function validar(string $codigo, array $credenciales): ResultadoCheckin
    {
        $apiKey = $credenciales['api_key'] ?? '';
        if ($apiKey === '') {
            return ResultadoCheckin::invalido('La integracion no tiene credenciales.');
        }

        $base = ($credenciales['base_url'] ?? '') !== '' ? $credenciales['base_url'] : $this->baseUrlPorDefecto();

        try {
            $respuesta = Http::withToken($apiKey)->acceptJson()->asJson()
                ->post(rtrim($base, '/').'/check-ins', ['code' => $codigo]);
        } catch (Throwable) {
            return ResultadoCheckin::invalido('No se pudo contactar al proveedor.');
        }

        if (! $respuesta->successful()) {
            return ResultadoCheckin::invalido('El proveedor rechazo el codigo.');
        }

        /** @var array<string, mixed> $datos */
        $datos = (array) $respuesta->json();
        $valido = ($datos['valid'] ?? null) === true || ($datos['status'] ?? '') === 'approved';

        if (! $valido) {
            return ResultadoCheckin::invalido('Codigo no valido o vencido.');
        }

        $referencia = (string) ($datos['id'] ?? $datos['check_in_id'] ?? $codigo);

        return ResultadoCheckin::valido($referencia, $this->usuario($datos));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function usuario(array $datos): ?string
    {
        if (isset($datos['user_name']) && is_string($datos['user_name'])) {
            return $datos['user_name'];
        }
        if (isset($datos['user']) && is_array($datos['user'])
            && isset($datos['user']['name']) && is_string($datos['user']['name'])) {
            return $datos['user']['name'];
        }

        return null;
    }
}
