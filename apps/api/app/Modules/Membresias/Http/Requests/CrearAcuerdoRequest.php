<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearAcuerdoRequest extends FormRequest
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
            'producto_id' => ['required', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
        ];
    }
}
