<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearPlantillaHorarioRequest extends FormRequest
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
            'sucursal_id' => ['required', 'string'],
            'recurso_id' => ['nullable', 'string'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'duracion_minutos' => ['required', 'integer', 'min:1'],
            'capacidad' => ['nullable', 'integer', 'min:1'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'reglas' => ['required', 'array', 'min:1'],
            'reglas.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'reglas.*.hora_inicio' => ['required', 'date_format:H:i'],
        ];
    }
}
