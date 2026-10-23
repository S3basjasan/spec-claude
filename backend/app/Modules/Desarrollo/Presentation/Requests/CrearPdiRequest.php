<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo para crear un plan en Borrador con sus compromisos, acciones e hitos. */
class CrearPdiRequest extends FormRequest
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
            'tipo' => ['required', Rule::in(TipoDePdi::valores())],
            'objetivo' => ['required', 'string', 'max:500'],
            'puesto_objetivo_id' => ['nullable', 'integer', 'min:1'],
            'responsable_id' => ['required', 'integer', 'min:1'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d'],
            'origen' => ['nullable', 'array'],
            'origen.tipo' => ['required_with:origen', Rule::in(['dnf', 'adecuacion'])],
            'origen.id' => ['required_with:origen', 'integer', 'min:1'],
            'compromisos' => ['nullable', 'array', 'max:100'],
            'compromisos.*.dimension' => ['required', Rule::in(DimensionDeCompromiso::valores())],
            'compromisos.*.tipo_elemento' => ['nullable', Rule::in(['Competencia', 'Conocimiento'])],
            'compromisos.*.elemento_id' => ['nullable', 'integer', 'min:1'],
            'compromisos.*.descripcion' => ['nullable', 'string', 'max:500'],
            'compromisos.*.nivel_actual' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'compromisos.*.nivel_objetivo' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'compromisos.*.indicador' => ['nullable', 'string', 'max:500'],
            'compromisos.*.fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'compromisos.*.fecha_fin' => ['required', 'date_format:Y-m-d'],
            'compromisos.*.acciones' => ['nullable', 'array', 'max:50'],
            'compromisos.*.acciones.*.tipo' => ['required', Rule::in(TipoDeAccionPdi::valores())],
            'compromisos.*.acciones.*.descripcion' => ['required', 'string', 'max:500'],
            'compromisos.*.acciones.*.persona_cargo' => ['nullable', 'string', 'max:150'],
            'compromisos.*.acciones.*.fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'compromisos.*.acciones.*.fecha_fin' => ['required', 'date_format:Y-m-d'],
            'compromisos.*.acciones.*.plan_accion_id' => ['nullable', 'integer', 'min:1'],
            'hitos' => ['nullable', 'array', 'max:100'],
            'hitos.*.fecha' => ['required', 'date_format:Y-m-d'],
            'hitos.*.descripcion' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colaborador_id.required' => 'Elige un colaborador.',
            'tipo.required' => 'Elige el tipo de plan.',
            'tipo.in' => 'El tipo de plan no es válido.',
            'objetivo.required' => 'El objetivo del plan es obligatorio.',
            'objetivo.max' => 'El objetivo no puede superar los 500 caracteres.',
            'responsable_id.required' => 'Elige el responsable del plan.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_inicio.date_format' => 'La fecha de inicio no es válida.',
            'fecha_fin.date_format' => 'La fecha de fin no es válida.',
            'compromisos.*.dimension.required' => 'Elige la dimensión del compromiso.',
            'compromisos.*.dimension.in' => 'La dimensión no es válida.',
            'compromisos.*.nivel_actual.in' => 'El nivel debe ser A, B, C o D.',
            'compromisos.*.nivel_objetivo.in' => 'El nivel debe ser A, B, C o D.',
            'compromisos.*.indicador.max' => 'El indicador no puede superar los 500 caracteres.',
            'compromisos.*.fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'compromisos.*.fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'compromisos.*.acciones.*.tipo.required' => 'Elige el tipo de acción.',
            'compromisos.*.acciones.*.tipo.in' => 'El tipo de acción no es válido.',
            'compromisos.*.acciones.*.descripcion.required' => 'La descripción de la acción es obligatoria.',
            'compromisos.*.acciones.*.descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'compromisos.*.acciones.*.persona_cargo.max' => 'La persona a cargo no puede superar los 150 caracteres.',
            'compromisos.*.acciones.*.fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'compromisos.*.acciones.*.fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'hitos.*.fecha.required' => 'La fecha del hito es obligatoria.',
            'hitos.*.descripcion.required' => 'La descripción del hito es obligatoria.',
            'hitos.*.descripcion.max' => 'La descripción del hito no puede superar los 300 caracteres.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return $this->validated();
    }
}
