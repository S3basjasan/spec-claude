<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Evaluación de impacto de una acción: todos los campos son obligatorios y el beneficio estimado (USD) no puede ser negativo. */
class GuardarImpactoRequest extends FormRequest
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
            'indicador' => ['required', 'string', 'max:150'],
            'unidad' => ['required', 'string', 'max:50'],
            'valor_antes' => ['required', 'numeric'],
            'valor_despues' => ['required', 'numeric'],
            'beneficio_estimado' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'justificacion' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'indicador.required' => 'El indicador de negocio es obligatorio.',
            'indicador.max' => 'El indicador de negocio no puede superar los 150 caracteres.',
            'unidad.required' => 'La unidad es obligatoria.',
            'unidad.max' => 'La unidad no puede superar los 50 caracteres.',
            'valor_antes.required' => 'El valor antes es obligatorio.',
            'valor_antes.numeric' => 'El valor antes debe ser un número.',
            'valor_despues.required' => 'El valor después es obligatorio.',
            'valor_despues.numeric' => 'El valor después debe ser un número.',
            'beneficio_estimado.required' => 'El beneficio estimado es obligatorio.',
            'beneficio_estimado.numeric' => 'El beneficio estimado debe ser un número.',
            'beneficio_estimado.min' => 'El beneficio estimado no puede ser negativo.',
            'beneficio_estimado.max' => 'El beneficio estimado es demasiado grande.',
            'justificacion.required' => 'La justificación es obligatoria.',
            'justificacion.max' => 'La justificación no puede superar los 2 000 caracteres.',
        ];
    }
}
