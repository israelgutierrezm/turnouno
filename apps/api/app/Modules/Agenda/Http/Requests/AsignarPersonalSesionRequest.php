<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Requests;

use App\Modules\Agenda\RolSesion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarPersonalSesionRequest extends FormRequest
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
            'rol' => ['nullable', Rule::enum(RolSesion::class)],
        ];
    }
}
