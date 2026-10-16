<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Formato de los datos de un plan en Borrador; las reglas de negocio las aplica el caso de uso. */
class GuardarPlanRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:120'],
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'aprobador_id' => ['nullable', 'integer', 'min:1'],
            'acciones' => ['nullable', 'array'],
            'acciones.*.id' => ['nullable', 'integer', 'min:1'],
            'acciones.*.nombre' => ['required', 'string', 'max:120'],
            'acciones.*.curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'acciones.*.tipo' => ['nullable', Rule::enum(TipoAccion::class)],
            'acciones.*.fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'acciones.*.fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:acciones.*.fecha_inicio'],
            'acciones.*.duracion_horas' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'acciones.*.formador' => ['nullable', 'string', 'max:120'],
            'acciones.*.capacidad_maxima' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'acciones.*.costo' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'acciones.*.competencia_ids' => ['required', 'array', 'min:1'],
            'acciones.*.competencia_ids.*' => ['integer', 'min:1'],
            'acciones.*.colaborador_ids' => ['nullable', 'array'],
            'acciones.*.colaborador_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede superar 120 caracteres.',
            'anio.required' => 'El año es obligatorio.',
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.between' => 'El año no es válido.',
            'descripcion.max' => 'La descripción no puede superar 2000 caracteres.',
            'acciones.*.nombre.required' => 'El nombre de la acción es obligatorio.',
            'acciones.*.nombre.max' => 'El nombre de la acción no puede superar 120 caracteres.',
            'acciones.*.curso_id.exists' => 'El curso del catálogo no existe.',
            'acciones.*.tipo.Illuminate\Validation\Rules\Enum' => 'El tipo debe ser Presencial, Virtual, ELearning, OJT, Codesarrollo o Mentoring.',
            'acciones.*.fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'acciones.*.fecha_fin.date_format' => 'La fecha de fin debe tener el formato AAAA-MM-DD.',
            'acciones.*.fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'acciones.*.duracion_horas.gt' => 'La duración debe ser mayor que cero.',
            'acciones.*.duracion_horas.numeric' => 'La duración debe ser un número.',
            'acciones.*.capacidad_maxima.min' => 'La capacidad máxima debe ser mayor que cero.',
            'acciones.*.capacidad_maxima.integer' => 'La capacidad máxima debe ser un número entero.',
            'acciones.*.costo.min' => 'El costo no puede ser negativo.',
            'acciones.*.costo.numeric' => 'El costo debe ser un número.',
            'acciones.*.competencia_ids.required' => 'Selecciona al menos una competencia objetivo.',
            'acciones.*.competencia_ids.min' => 'Selecciona al menos una competencia objetivo.',
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
