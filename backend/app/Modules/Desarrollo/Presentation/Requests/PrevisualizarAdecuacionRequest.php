<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Colaborador y puesto objetivo (por inscripción o puesto directo) del diagnóstico que se quiere calcular. */
class PrevisualizarAdecuacionRequest extends FormRequest
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
            'colaborador_id' => ['required', 'integer', 'min:1'],
            'inscripcion_id' => ['nullable', 'integer', 'min:1'],
            'puesto_objetivo_id' => ['nullable', 'integer', 'min:1'],
            'experiencia_requerida_anios' => ['nullable', 'numeric', 'min:0', 'max:60', 'decimal:0,1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colaborador_id.required' => 'Elige un colaborador.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'inscripcion_id.integer' => 'La inscripción no es válida.',
            'puesto_objetivo_id.integer' => 'El puesto objetivo no es válido.',
            'experiencia_requerida_anios.numeric' => 'La experiencia requerida debe ser un número de años.',
            'experiencia_requerida_anios.min' => 'La experiencia requerida no puede ser negativa.',
            'experiencia_requerida_anios.max' => 'La experiencia requerida no puede superar los 60 años.',
            'experiencia_requerida_anios.decimal' => 'La experiencia requerida admite como máximo un decimal.',
        ];
    }

    public function colaboradorId(): int
    {
        return (int) $this->validated()['colaborador_id'];
    }

    public function inscripcionId(): ?int
    {
        $v = $this->validated();

        return isset($v['inscripcion_id']) ? (int) $v['inscripcion_id'] : null;
    }

    public function puestoObjetivoId(): ?int
    {
        $v = $this->validated();

        return isset($v['puesto_objetivo_id']) ? (int) $v['puesto_objetivo_id'] : null;
    }

    public function experienciaRequerida(): ?float
    {
        $v = $this->validated();

        return isset($v['experiencia_requerida_anios']) ? (float) $v['experiencia_requerida_anios'] : null;
    }
}
