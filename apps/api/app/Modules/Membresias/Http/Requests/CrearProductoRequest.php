<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Requests;

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
        ];
    }
}
