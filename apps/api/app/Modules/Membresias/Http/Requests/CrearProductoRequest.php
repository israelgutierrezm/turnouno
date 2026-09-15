<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Requests;

use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Membresias\TipoProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoProducto::class)],
            'precio_minor' => ['required', 'integer', 'min:0'],
            'moneda' => ['required', 'string', 'size:3'],
            'ilimitado' => ['sometimes', 'boolean'],
            'creditos_incluidos' => ['nullable', 'integer', 'min:0'],
            // Plantilla de ciclo/rollover (Slice 5c). Opcionales; por defecto pack sin reinicio.
            'politica_reset' => ['sometimes', Rule::enum(PoliticaReset::class)],
            'unidades_por_ciclo' => ['nullable', 'integer', 'min:1'],
            'politica_rollover' => ['sometimes', Rule::enum(PoliticaRollover::class)],
            'rollover_max' => ['nullable', 'integer', 'min:0'],
            // Restricciones opcionales (ULID de actividad/sucursal del tenant).
            'actividad_id' => ['nullable', 'string'],
            'sucursal_id' => ['nullable', 'string'],
        ];
    }
}
