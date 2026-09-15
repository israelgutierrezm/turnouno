<?php

declare(strict_types=1);

namespace App\Modules\Ordenes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearOrdenRequest extends FormRequest
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
            'persona_id' => ['required', 'string'],
            // F-18: límites de tamaño server-side para evitar fulfillment masivo.
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id' => ['required', 'string'],
            'items.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:100'],
            'items.*.beneficiario_id' => ['nullable', 'string'],
        ];
    }
}
