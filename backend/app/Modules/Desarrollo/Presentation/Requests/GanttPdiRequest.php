<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros y paginación (por plan) de la carta Gantt. */
class GanttPdiRequest extends FormRequest
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
            'responsable_id' => ['nullable', 'integer', 'min:1'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['tipo.in' => 'El tipo de plan no es válido.', 'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.'];
    }

    /** @return array<string, mixed> */
    public function filtros(): array
    {
        return array_filter($this->safe()->only(['tipo', 'responsable_id', 'departamento_id']), fn ($v) => $v !== null && $v !== '');
    }

    public function pagina(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated()['per_page'] ?? 10);
    }
}
