<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\FiltroCalificaciones;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarCalificacionesRequest extends FormRequest
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
            'agrupar' => ['required', Rule::in(['actividad', 'participante'])],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('fecha_desde'), ['after_or_equal:fecha_desde'])],
            'tipo' => ['nullable', Rule::enum(TipoAccion::class)],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'colaborador_id' => ['nullable', 'integer', 'min:1', Rule::prohibitedIf(fn () => $this->input('agrupar') !== 'participante')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agrupar.required' => 'Indica si agrupar por actividad o por participante.',
            'agrupar.in' => 'Agrupar debe ser actividad o participante.',
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la final.',
            'tipo.Illuminate\Validation\Rules\Enum' => 'El tipo debe ser Presencial, Virtual, ELearning, OJT, Codesarrollo o Mentoring.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'plan_id.integer' => 'El plan no es válido.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'colaborador_id.prohibited' => 'El colaborador solo se puede indicar al agrupar por participante.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 200.',
        ];
    }

    public function filtro(): FiltroCalificaciones
    {
        $d = $this->validated();

        return new FiltroCalificaciones(
            $d['agrupar'],
            $d['fecha_desde'] ?? null,
            $d['fecha_hasta'] ?? null,
            isset($d['tipo']) ? TipoAccion::from($d['tipo']) : null,
            isset($d['departamento_id']) ? (int) $d['departamento_id'] : null,
            isset($d['plan_id']) ? (int) $d['plan_id'] : null,
            isset($d['colaborador_id']) ? (int) $d['colaborador_id'] : null,
            (int) ($d['page'] ?? 1),
            (int) ($d['per_page'] ?? 15),
        );
    }
}
