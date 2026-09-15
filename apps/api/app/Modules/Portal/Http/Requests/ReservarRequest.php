<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservarRequest extends FormRequest
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
            'sesion_id' => ['required', 'string'],
            'esperar' => ['nullable', 'boolean'],
        ];
    }
}
