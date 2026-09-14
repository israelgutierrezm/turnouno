<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearOrganizacionRequest extends FormRequest
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
