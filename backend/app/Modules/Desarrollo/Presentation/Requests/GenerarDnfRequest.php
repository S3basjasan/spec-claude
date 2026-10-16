<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerarDnfRequest extends FormRequest
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
            'alcance' => ['required', Rule::enum(AlcanceDnf::class)],
            'colaborador_id' => ['required_if:alcance,Individual', 'prohibited_if:alcance,Departamento', 'nullable', 'integer', 'min:1'],
            'departamento_id' => ['required_if:alcance,Departamento', 'prohibited_if:alcance,Individual', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alcance.required' => 'Elige el alcance de la detección.',
            'alcance.Illuminate\Validation\Rules\Enum' => 'El alcance debe ser Individual o Departamento.',
            'colaborador_id.required_if' => 'Elige un colaborador.',
            'colaborador_id.prohibited_if' => 'No indiques un colaborador en una DNF por departamento.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'colaborador_id.min' => 'El colaborador no es válido.',
            'departamento_id.required_if' => 'Elige un departamento.',
            'departamento_id.prohibited_if' => 'No indiques un departamento en una DNF individual.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'departamento_id.min' => 'El departamento no es válido.',
        ];
    }

    public function alcance(): AlcanceDnf
    {
        return AlcanceDnf::from((string) $this->validated('alcance'));
    }

    public function colaboradorId(): ?int
    {
        $valor = $this->validated('colaborador_id');

        return $valor === null ? null : (int) $valor;
    }

    public function departamentoId(): ?int
    {
        $valor = $this->validated('departamento_id');

        return $valor === null ? null : (int) $valor;
    }
}
