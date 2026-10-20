<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroActividades;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarActividadesRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', Rule::enum(TipoAccion::class)],
            'estado' => ['nullable', Rule::enum(EstadoAccion::class)],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('fecha_desde'), ['after_or_equal:fecha_desde'])],
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
            'q.max' => 'La búsqueda no puede superar los 120 caracteres.',
            'tipo.Illuminate\Validation\Rules\Enum' => 'El tipo debe ser Presencial, Virtual, ELearning, OJT, Codesarrollo o Mentoring.',
            'estado.Illuminate\Validation\Rules\Enum' => 'El estado debe ser Programada, EnCurso, Finalizada o Cancelada.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'competencia_id.integer' => 'La competencia no es válida.',
            'plan_id.integer' => 'El plan no es válido.',
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la final.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 200.',
        ];
    }

    public function filtro(): FiltroActividades
    {
        $d = $this->validated();

        return new FiltroActividades(
            $d['q'] ?? null,
            isset($d['tipo']) ? TipoAccion::from($d['tipo']) : null,
            isset($d['estado']) ? EstadoAccion::from($d['estado']) : null,
            isset($d['departamento_id']) ? (int) $d['departamento_id'] : null,
            isset($d['competencia_id']) ? (int) $d['competencia_id'] : null,
            isset($d['plan_id']) ? (int) $d['plan_id'] : null,
            $d['fecha_desde'] ?? null,
            $d['fecha_hasta'] ?? null,
            (int) ($d['page'] ?? 1),
            (int) ($d['per_page'] ?? 15),
        );
    }
}
