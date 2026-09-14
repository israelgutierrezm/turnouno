<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearReservaRequest extends FormRequest
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
            'persona_id' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
