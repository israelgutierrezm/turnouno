<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarDependienteRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'parentesco' => ['nullable', 'string', 'max:255'],
        ];
    }
}
