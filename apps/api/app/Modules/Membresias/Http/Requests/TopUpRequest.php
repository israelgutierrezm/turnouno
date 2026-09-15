<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
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
            'unidades' => ['required', 'integer', 'min:1'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
