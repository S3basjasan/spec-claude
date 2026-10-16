<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroAcciones;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarAccionesRequest extends FormRequest
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
            'estado' => ['nullable', Rule::enum(EstadoPlan::class)],
            'tipo' => ['nullable', Rule::enum(TipoAccion::class)],
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('fecha_desde'), ['after_or_equal:fecha_desde'])],
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'plan_id' => ['nullable', 'integer', 'min:1'],
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
            'estado.Illuminate\Validation\Rules\Enum' => 'El estado del plan debe ser Borrador, Revision, Aprobado, Ejecutando o Cerrado.',
            'tipo.Illuminate\Validation\Rules\Enum' => 'El tipo debe ser Presencial, Virtual, ELearning, OJT, Codesarrollo o Mentoring.',
            'competencia_id.integer' => 'La competencia no es válida.',
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la final.',
            'anio.between' => 'El año no es válido.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 200.',
        ];
    }

    public function filtro(): FiltroAcciones
    {
        $d = $this->validated();

        return new FiltroAcciones(
            isset($d['estado']) ? EstadoPlan::from($d['estado']) : null,
            isset($d['tipo']) ? TipoAccion::from($d['tipo']) : null,
            isset($d['competencia_id']) ? (int) $d['competencia_id'] : null,
            $d['fecha_desde'] ?? null,
            $d['fecha_hasta'] ?? null,
            isset($d['anio']) ? (int) $d['anio'] : null,
            isset($d['plan_id']) ? (int) $d['plan_id'] : null,
            (int) ($d['page'] ?? 1),
            (int) ($d['per_page'] ?? 10),
        );
    }
}
