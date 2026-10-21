<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarPreguntasRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'preguntas' => ['required', 'array', 'size:4'],
            'preguntas.*' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión de la competencia. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión de la competencia no es válida.',
            'version.min' => 'La versión de la competencia no es válida.',
            'preguntas.required' => 'Completa las cuatro preguntas.',
            'preguntas.array' => 'Completa las cuatro preguntas.',
            'preguntas.size' => 'Completa las cuatro preguntas.',
            'preguntas.*.required' => 'Completa las cuatro preguntas.',
            'preguntas.*.string' => 'Completa las cuatro preguntas.',
            'preguntas.*.max' => 'Una pregunta no puede superar los 500 caracteres.',
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }

    /**
     * @return array<int, string>
     */
    public function preguntas(): array
    {
        return array_map('strval', array_values($this->validated('preguntas')));
    }
}
