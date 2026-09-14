<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearSesionUnicaRequest extends FormRequest
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
            'oferta_id' => ['required', 'string'],
            'recurso_id' => ['nullable', 'string'],
            'inicia_en_local' => ['required', 'date_format:Y-m-d H:i'],
            'duracion_minutos' => ['required', 'integer', 'min:1'],
            'capacidad' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
