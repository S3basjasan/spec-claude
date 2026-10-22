<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponderRequest extends FormRequest
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
            'pregunta_id' => ['required', 'integer', 'min:1'],
            'opcion_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pregunta_id.required' => 'Indica la pregunta.',
            'pregunta_id.integer' => 'La pregunta no es válida.',
            'opcion_id.required' => 'Elige una opción.',
            'opcion_id.integer' => 'La opción no es válida.',
        ];
    }
}
