<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AplicarEscalaRequest extends FormRequest
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
            'tipo_grado_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_grado_id.required' => 'Selecciona la escala de los grados.',
            'tipo_grado_id.integer' => 'Selecciona la escala de los grados.',
        ];
    }

    public function escalaId(): int
    {
        return (int) $this->validated('tipo_grado_id');
    }
}
