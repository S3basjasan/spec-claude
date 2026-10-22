<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Retira una inscripción: el motivo es obligatorio. */
class RetirarRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo del retiro es obligatorio.',
            'motivo.string' => 'El motivo debe ser texto.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }

    public function motivo(): string
    {
        return (string) $this->validated()['motivo'];
    }
}
