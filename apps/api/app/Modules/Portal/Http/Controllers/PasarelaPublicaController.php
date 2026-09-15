<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\Pasarelas\RegistroDePasarelas;
use Illuminate\Http\JsonResponse;

/**
 * Pasarelas disponibles para el miembro con sus llaves PÚBLICAS (publishable /
 * merchant id / instrucciones), que el cliente usa para inicializar los SDKs.
 * Nunca expone secretos.
 */
class PasarelaPublicaController
{
    /**
     * @var array<string, list<string>>
     */
    private const PUBLICAS = [
        'stripe' => ['public_key'],
        'openpay' => ['merchant_id', 'public_key'],
        'mercadopago' => ['public_key'],
        'ventanilla' => ['instrucciones'],
    ];

    public function __construct(private readonly RegistroDePasarelas $registro) {}

    public function index(): JsonResponse
    {
        $disponibles = $this->registro->disponibles();

        $configs = ConfiguracionPasarela::query()
            ->whereIn('proveedor', array_keys(self::PUBLICAS))
            ->get()
            ->keyBy('proveedor');

        $data = [];
        foreach ($disponibles as $proveedor) {
            $config = $configs->get($proveedor);
            $llaves = [];

            foreach (self::PUBLICAS[$proveedor] ?? [] as $llave) {
                $valor = $config instanceof ConfiguracionPasarela ? $config->llave($llave) : null;
                if ($valor !== null) {
                    $llaves[$llave] = $valor;
                }
            }

            $data[] = [
                'proveedor' => $proveedor,
                'modo' => $config instanceof ConfiguracionPasarela ? $config->modo : 'test',
                'llaves' => $llaves,
            ];
        }

        return response()->json(['data' => $data]);
    }
}
