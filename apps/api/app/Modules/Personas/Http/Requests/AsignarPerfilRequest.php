<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Requests;

use App\Modules\Personas\TipoPerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarPerfilRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoPerfil::class)],
        ];
    }
}
