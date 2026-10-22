<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarComportamientosRequest extends FormRequest
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
            'comportamientos' => ['required', 'array', 'size:4'],
            'comportamientos.*' => ['required', 'array', 'size:4'],
            'comportamientos.*.*' => ['required', 'string', 'max:300'],
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
            'comportamientos.required' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.array' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.size' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.required' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.array' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.size' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.*.required' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.*.string' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.*.max' => 'Un comportamiento no puede superar los 300 caracteres.',
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function comportamientos(): array
    {
        return array_map(
            fn (array $textos) => array_map('strval', array_values($textos)),
            array_values($this->validated('comportamientos')),
        );
    }
}
