<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Requests;

use App\Modules\Personas\TipoPerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearPersonaRequest extends FormRequest
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
            'apellidos' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'hogar_id' => ['nullable', 'string'],
            'perfiles' => ['nullable', 'array'],
            'perfiles.*' => [Rule::enum(TipoPerfil::class)],
        ];
    }
}
