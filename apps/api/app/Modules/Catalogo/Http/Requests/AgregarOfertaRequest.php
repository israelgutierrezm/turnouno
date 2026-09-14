<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Http\Requests;

use App\Modules\Catalogo\ModalidadOferta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgregarOfertaRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'modalidad' => ['required', Rule::enum(ModalidadOferta::class)],
            'capacidad' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
