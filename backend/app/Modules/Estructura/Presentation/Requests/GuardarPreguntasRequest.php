<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GuardarPreguntasRequest extends FormRequest
{
    public const CANTIDAD = 4;

    public const MAX_CARACTERES = 500;

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
            'competencia_id' => $this->isMethod('POST') ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:1'],
            // Exactamente cuatro preguntas, numeradas por posición.
            'preguntas' => ['required', 'array', 'size:'.self::CANTIDAD],
            'preguntas.*' => ['required', 'string', 'max:'.self::MAX_CARACTERES],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'competencia_id.required' => 'Selecciona la competencia.',
            'competencia_id.integer' => 'Selecciona una competencia válida.',
            'competencia_id.min' => 'Selecciona una competencia válida.',
            'preguntas.required' => 'Ingresa las cuatro preguntas.',
            'preguntas.array' => 'Las preguntas deben ser una lista de cuatro textos.',
            'preguntas.size' => 'Debes ingresar exactamente '.self::CANTIDAD.' preguntas.',
            'preguntas.*.required' => 'La pregunta no puede estar vacía.',
            'preguntas.*.string' => 'La pregunta no es válida.',
            'preguntas.*.max' => 'Cada pregunta admite como máximo '.self::MAX_CARACTERES.' caracteres.',
            'version.required' => 'Falta la versión del registro. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión del registro no es válida.',
            'version.min' => 'La versión del registro no es válida.',
        ];
    }

    /**
     * Una pregunta no puede repetirse dentro del registro (sin distinguir mayúsculas ni espacios alrededor).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $preguntas = $this->input('preguntas');
            if (! is_array($preguntas)) {
                return;
            }

            $vistas = [];
            foreach (array_values($preguntas) as $indice => $texto) {
                if (! is_string($texto) || trim($texto) === '') {
                    continue;
                }
                $clave = mb_strtolower(trim($texto));
                if (isset($vistas[$clave])) {
                    $validator->errors()->add("preguntas.$indice", 'Esta pregunta está repetida en el registro.');
                }
                $vistas[$clave] = true;
            }
        });
    }

    /**
     * Las cuatro preguntas, recortadas y en el orden recibido.
     *
     * @return array<int, string>
     */
    public function preguntas(): array
    {
        return array_map(fn ($texto) => trim((string) $texto), array_values((array) $this->validated('preguntas')));
    }
}
