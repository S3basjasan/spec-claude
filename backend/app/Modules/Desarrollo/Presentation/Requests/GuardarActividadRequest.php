<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Crea (con `plan_accion_id`) o reemplaza (solo lugar o enlace y departamento) una actividad. */
class GuardarActividadRequest extends FormRequest
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
            ...($this->isMethod('POST') ? ['plan_accion_id' => ['required', 'integer', 'min:1']] : []),
            'lugar_enlace' => ['required', 'string', 'max:500'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_accion_id.required' => 'Elige la acción del plan.',
            'plan_accion_id.integer' => 'La acción del plan no es válida.',
            'plan_accion_id.min' => 'La acción del plan no es válida.',
            'lugar_enlace.required' => 'El lugar o enlace es obligatorio.',
            'lugar_enlace.string' => 'El lugar o enlace debe ser texto.',
            'lugar_enlace.max' => 'El lugar o enlace no puede superar los 500 caracteres.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'departamento_id.min' => 'El departamento no es válido.',
        ];
    }

    public function accionId(): int
    {
        return (int) $this->validated()['plan_accion_id'];
    }

    public function lugarEnlace(): string
    {
        return (string) $this->validated()['lugar_enlace'];
    }

    public function departamentoId(): ?int
    {
        $d = $this->validated()['departamento_id'] ?? null;

        return $d === null ? null : (int) $d;
    }
}
