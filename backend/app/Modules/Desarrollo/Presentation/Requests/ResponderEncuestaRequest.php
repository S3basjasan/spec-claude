<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Encuesta de satisfacción: calificación general entera de 1 a 5 y comentario opcional. */
class ResponderEncuestaRequest extends FormRequest
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
            'actividad_id' => ['required', 'integer', 'min:1'],
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'actividad_id.required' => 'La actividad es obligatoria.',
            'calificacion.required' => 'La calificación es obligatoria.',
            'calificacion.integer' => 'La calificación debe ser un número entero de 1 a 5.',
            'calificacion.between' => 'La calificación debe estar entre 1 y 5.',
            'comentario.max' => 'El comentario no puede superar los 1 000 caracteres.',
        ];
    }
}
