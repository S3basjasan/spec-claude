<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GuardarComportamientosRequest extends FormRequest
{
    public const MAX_POR_GRADO = 4;

    public const MAX_CARACTERES = 300;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reglas = [
            'competencia_id' => $this->isMethod('POST') ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:1'],
            // Exactamente A, B, C y D: se rechazan claves extra.
            'comportamientos' => ['required', 'array:'.implode(',', GradoLetra::letras())],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
        ];

        foreach (GradoLetra::letras() as $letra) {
            $reglas["comportamientos.$letra"] = ['required', 'array', 'min:1', 'max:'.self::MAX_POR_GRADO];
            $reglas["comportamientos.$letra.*"] = ['required', 'string', 'max:'.self::MAX_CARACTERES];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [
            'competencia_id.required' => 'Selecciona la competencia.',
            'competencia_id.integer' => 'Selecciona una competencia válida.',
            'competencia_id.min' => 'Selecciona una competencia válida.',
            'comportamientos.required' => 'Ingresa los comportamientos de los cuatro grados.',
            'comportamientos.array' => 'Los grados deben ser exactamente A, B, C y D, sin repetir.',
            'version.required' => 'Falta la versión del registro. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión del registro no es válida.',
            'version.min' => 'La versión del registro no es válida.',
        ];

        foreach (GradoLetra::letras() as $letra) {
            $mensajes["comportamientos.$letra.required"] = "Ingresa al menos un comportamiento para el grado $letra.";
            $mensajes["comportamientos.$letra.array"] = "Los comportamientos del grado $letra deben ser una lista.";
            $mensajes["comportamientos.$letra.min"] = "Ingresa al menos un comportamiento para el grado $letra.";
            $mensajes["comportamientos.$letra.max"] = 'Cada grado admite como máximo '.self::MAX_POR_GRADO." comportamientos (grado $letra).";
            $mensajes["comportamientos.$letra.*.required"] = "El comportamiento del grado $letra no puede estar vacío.";
            $mensajes["comportamientos.$letra.*.string"] = "El comportamiento del grado $letra no es válido.";
            $mensajes["comportamientos.$letra.*.max"] = 'Cada comportamiento admite como máximo '.self::MAX_CARACTERES." caracteres (grado $letra).";
        }

        return $mensajes;
    }

    /**
     * Un comportamiento no puede repetirse dentro del mismo grado (sin distinguir mayúsculas ni espacios alrededor).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $comportamientos = $this->input('comportamientos');
            if (! is_array($comportamientos)) {
                return;
            }

            foreach (GradoLetra::letras() as $letra) {
                $textos = $comportamientos[$letra] ?? null;
                if (! is_array($textos)) {
                    continue;
                }

                $vistos = [];
                foreach (array_values($textos) as $indice => $texto) {
                    if (! is_string($texto) || trim($texto) === '') {
                        continue;
                    }
                    $clave = mb_strtolower(trim($texto));
                    if (isset($vistos[$clave])) {
                        $validator->errors()->add("comportamientos.$letra.$indice", "Este comportamiento está repetido en el grado $letra.");
                    }
                    $vistos[$clave] = true;
                }
            }
        });
    }

    /**
     * Textos por letra, recortados y en el orden recibido.
     *
     * @return array<string, array<int, string>>
     */
    public function comportamientos(): array
    {
        $resultado = [];
        foreach (GradoLetra::letras() as $letra) {
            $resultado[$letra] = array_map(fn ($texto) => trim((string) $texto), array_values((array) $this->validated("comportamientos.$letra")));
        }

        return $resultado;
    }
}
