<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReasignarAprobadorRequest extends FormRequest
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
        return ['aprobador_id' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aprobador_id.required' => 'Selecciona un aprobador.',
            'aprobador_id.integer' => 'El aprobador no es válido.',
            'aprobador_id.min' => 'El aprobador no es válido.',
        ];
    }

    public function aprobadorId(): int
    {
        return (int) $this->validated()['aprobador_id'];
    }
}
