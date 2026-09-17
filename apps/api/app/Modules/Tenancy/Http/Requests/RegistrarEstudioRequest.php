<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\PerfilNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrarEstudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9-]+$/', 'unique:estudios,slug'],
            'perfil_negocio' => ['nullable', Rule::enum(PerfilNegocio::class)],
            'contacto_nombre' => ['required', 'string', 'max:255'],
            'contacto_email' => ['required', 'email', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:40'],
            'pais' => ['nullable', 'string', 'size:2'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'zona_horaria' => ['nullable', 'timezone'],
            'acepta_terminos' => ['accepted'],
        ];
    }
}
