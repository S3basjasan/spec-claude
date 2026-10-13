<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoPuestoRequest extends FormRequest
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
            'estado' => ['required', 'string', Rule::in(['Activo', 'Inactivo'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Indica el estado: Activo o Inactivo.',
            'estado.string' => 'El estado debe ser Activo o Inactivo.',
            'estado.in' => 'El estado debe ser Activo o Inactivo.',
        ];
    }

    public function estado(): string
    {
        return (string) $this->validated('estado');
    }
}
