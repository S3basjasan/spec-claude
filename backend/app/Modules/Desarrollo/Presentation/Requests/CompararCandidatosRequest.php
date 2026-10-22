<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Puesto, candidatos y, opcionalmente, los pesos de la consulta (los tres juntos). */
class CompararCandidatosRequest extends FormRequest
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
            'puesto_objetivo_id' => ['required', 'integer', 'min:1'],
            'colaborador_ids' => ['required', 'array', 'min:2', 'max:20'],
            'colaborador_ids.*' => ['integer', 'min:1', 'distinct'],
            'peso_competencias' => ['nullable', 'integer', 'min:0', 'max:100', 'required_with:peso_conocimientos,peso_experiencia'],
            'peso_conocimientos' => ['nullable', 'integer', 'min:0', 'max:100', 'required_with:peso_competencias,peso_experiencia'],
            'peso_experiencia' => ['nullable', 'integer', 'min:0', 'max:100', 'required_with:peso_competencias,peso_conocimientos'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'puesto_objetivo_id.required' => 'Elige el puesto a cubrir.',
            'colaborador_ids.required' => 'Elige al menos dos candidatos.',
            'colaborador_ids.min' => 'Elige al menos dos candidatos.',
            'colaborador_ids.max' => 'Puedes comparar hasta 20 candidatos.',
            'colaborador_ids.*.distinct' => 'Un candidato no puede repetirse.',
            'colaborador_ids.*.integer' => 'Un candidato no es válido.',
            'peso_competencias.integer' => 'Los pesos deben ser números enteros.',
            'peso_conocimientos.integer' => 'Los pesos deben ser números enteros.',
            'peso_experiencia.integer' => 'Los pesos deben ser números enteros.',
            'peso_competencias.min' => 'Los pesos no pueden ser negativos.',
            'peso_conocimientos.min' => 'Los pesos no pueden ser negativos.',
            'peso_experiencia.min' => 'Los pesos no pueden ser negativos.',
            'peso_competencias.required_with' => 'Indica los tres pesos.',
            'peso_conocimientos.required_with' => 'Indica los tres pesos.',
            'peso_experiencia.required_with' => 'Indica los tres pesos.',
        ];
    }

    public function puestoObjetivoId(): int
    {
        return (int) $this->validated()['puesto_objetivo_id'];
    }

    /** @return list<int> */
    public function colaboradorIds(): array
    {
        return array_values(array_map('intval', $this->validated()['colaborador_ids']));
    }

    /** @return array{competencias: int, conocimientos: int, experiencia: int}|null */
    public function pesos(): ?array
    {
        $v = $this->validated();
        if (! isset($v['peso_competencias'], $v['peso_conocimientos'], $v['peso_experiencia'])) {
            return null;
        }

        return ['competencias' => (int) $v['peso_competencias'], 'conocimientos' => (int) $v['peso_conocimientos'], 'experiencia' => (int) $v['peso_experiencia']];
    }
}
