<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurarPasarelaRequest extends FormRequest
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
            'activa' => ['required', 'boolean'],
            'modo' => ['required', Rule::in(['test', 'live'])],
            'credenciales' => ['nullable', 'array'],
            'credenciales.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
