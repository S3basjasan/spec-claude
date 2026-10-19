<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Seguimiento a 30, 60 o 90 días: dos calificaciones enteras de 1 a 5 y comentario opcional. */
class ResponderSeguimientoRequest extends FormRequest
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
            'hito' => ['required', 'integer'],
            'aplicacion' => ['required', 'integer', 'between:1,5'],
            'apoyo' => ['required', 'integer', 'between:1,5'],
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
            'hito.required' => 'El hito es obligatorio.',
            'hito.integer' => 'El hito debe ser 30, 60 o 90 días.',
            'aplicacion.required' => 'La calificación de aplicación es obligatoria.',
            'aplicacion.integer' => 'La aplicación debe ser un número entero de 1 a 5.',
            'aplicacion.between' => 'La aplicación debe estar entre 1 y 5.',
            'apoyo.required' => 'La calificación de apoyo del entorno es obligatoria.',
            'apoyo.integer' => 'El apoyo debe ser un número entero de 1 a 5.',
            'apoyo.between' => 'El apoyo debe estar entre 1 y 5.',
            'comentario.max' => 'El comentario no puede superar los 1 000 caracteres.',
        ];
    }
}
