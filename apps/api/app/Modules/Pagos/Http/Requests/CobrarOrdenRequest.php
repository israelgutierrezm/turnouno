<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Requests;

use App\Modules\Pagos\Pasarelas\RegistroDePasarelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CobrarOrdenRequest extends FormRequest
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
            'proveedor' => ['required', 'string', Rule::in(app(RegistroDePasarelas::class)->disponibles())],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
