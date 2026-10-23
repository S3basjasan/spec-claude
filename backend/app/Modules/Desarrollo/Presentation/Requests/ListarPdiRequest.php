<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros y paginación de la lista de planes. */
class ListarPdiRequest extends FormRequest
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
            'tipo' => ['nullable', Rule::in(TipoDePdi::valores())],
            'estado' => ['nullable', Rule::in(['Borrador', 'Acordado', 'Cerrado'])],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'responsable_id' => ['nullable', 'integer', 'min:1'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo de plan no es válido.',
            'estado.in' => 'El estado no es válido.',
            'fecha_desde.date_format' => 'La fecha inicial no es válida.',
            'fecha_hasta.date_format' => 'La fecha final no es válida.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    /** @return array<string, mixed> */
    public function filtros(): array
    {
        return array_filter(
            $this->safe()->only(['tipo', 'estado', 'colaborador_id', 'departamento_id', 'responsable_id', 'fecha_desde', 'fecha_hasta']),
            fn ($v) => $v !== null && $v !== '',
        );
    }

    public function pagina(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated()['per_page'] ?? 15);
    }
}
