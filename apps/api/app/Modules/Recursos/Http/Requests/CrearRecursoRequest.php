<?php

declare(strict_types=1);

namespace App\Modules\Recursos\Http\Requests;

use App\Modules\Recursos\ModoRecurso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearRecursoRequest extends FormRequest
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
            'modo' => ['required', Rule::enum(ModoRecurso::class)],
            'capacidad' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'recurso_padre_id' => ['nullable', 'string'],
        ];
    }
}
