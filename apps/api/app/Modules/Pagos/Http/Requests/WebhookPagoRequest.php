<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookPagoRequest extends FormRequest
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
            'referencia' => ['required', 'string'],
            'estado' => ['required', 'string', Rule::in(['aprobado', 'rechazado'])],
        ];
    }
}
