<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros comunes de los reportes (período, departamento, tipo de formación y plan) más búsqueda y paginación. */
class FiltrosReportesRequest extends FormRequest
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
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', Rule::in(FiltroReportes::tiposValidos())],
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'tipo.in' => 'El tipo de formación no es válido.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'plan_id.integer' => 'El plan no es válido.',
        ];
    }

    public function pagina(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
