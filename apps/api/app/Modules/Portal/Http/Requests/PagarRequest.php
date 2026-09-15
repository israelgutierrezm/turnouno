<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Requests;

use App\Modules\Pagos\MetodoPago;
use App\Modules\Pagos\Pasarelas\RegistroDePasarelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagarRequest extends FormRequest
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
            'metodo' => ['nullable', Rule::enum(MetodoPago::class)],
            'card_token' => ['nullable', 'string', 'max:255'],
            'device_session_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
