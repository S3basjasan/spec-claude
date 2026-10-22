<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Pesos por dimensión (enteros que suman 100) y umbral de preparación en meses. */
class ActualizarParametrosRequest extends FormRequest
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
            'pesos' => ['required', 'array'],
            'pesos.Competencia' => ['required', 'integer', 'min:0', 'max:100'],
            'pesos.Conocimiento' => ['required', 'integer', 'min:0', 'max:100'],
            'pesos.Experiencia' => ['required', 'integer', 'min:0', 'max:100'],
            'umbral_meses' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pesos.required' => 'Indica los tres pesos.',
            'pesos.*.required' => 'Indica el peso.',
            'pesos.*.integer' => 'El peso debe ser un número entero.',
            'pesos.*.min' => 'El peso no puede ser negativo.',
            'pesos.*.max' => 'El peso no puede superar 100.',
            'umbral_meses.required' => 'Indica el umbral de preparación en meses.',
            'umbral_meses.integer' => 'El umbral debe ser un número entero de meses.',
            'umbral_meses.min' => 'El umbral debe ser de al menos un mes.',
            'umbral_meses.max' => 'El umbral no puede superar 120 meses.',
        ];
    }

    /** @return array{competencias: int, conocimientos: int, experiencia: int} */
    public function pesos(): array
    {
        $p = $this->validated()['pesos'];

        return ['competencias' => (int) $p['Competencia'], 'conocimientos' => (int) $p['Conocimiento'], 'experiencia' => (int) $p['Experiencia']];
    }

    public function umbralMeses(): int
    {
        return (int) $this->validated()['umbral_meses'];
    }
}
