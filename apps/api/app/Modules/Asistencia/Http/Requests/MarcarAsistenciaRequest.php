<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Http\Requests;

use App\Modules\Asistencia\EstadoAsistencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarcarAsistenciaRequest extends FormRequest
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
            'estado' => ['required', Rule::enum(EstadoAsistencia::class)],
        ];
    }
}
