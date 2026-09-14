<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetenerRequest extends FormRequest
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
            'unidades' => ['required', 'integer', 'min:1'],
        ];
    }
}
