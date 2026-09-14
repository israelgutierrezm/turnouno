<?php

declare(strict_types=1);

namespace App\Modules\Recursos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearInstalacionRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
        ];
    }
}
