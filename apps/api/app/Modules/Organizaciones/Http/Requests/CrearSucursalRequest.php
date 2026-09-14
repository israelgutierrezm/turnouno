<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearSucursalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'organizacion_id' => ['required', 'string'],
            'nombre' => ['required', 'string', 'max:255'],
            'zona_horaria' => ['nullable', 'string', 'max:255'],
        ];
    }
}
