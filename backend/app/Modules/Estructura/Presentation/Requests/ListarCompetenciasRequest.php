<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Application\DTO\FiltroCompetenciasDTO;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarCompetenciasRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', Rule::in(array_map(fn (TipoCompetencia $t) => $t->value, TipoCompetencia::cases()))],
            'estado' => ['nullable', 'string', Rule::in(['Activo', 'Inactivo', 'Todos'])],
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
            'q.max' => 'La búsqueda no puede superar los 50 caracteres.',
            'tipo.in' => 'El tipo debe ser Cardinal o Especifica.',
            'estado.in' => 'El estado debe ser Activo, Inactivo o Todos.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    /**
     * Resuelve el estado efectivo según el rol: quien no es administrador solo recibe competencias
     * `Activo` (el parámetro se ignora); el administrador recibe `Activo` por defecto, o `Inactivo`/`Todos`.
     */
    public function filtro(bool $esAdministrador): FiltroCompetenciasDTO
    {
        $pedido = $esAdministrador ? ($this->validated('estado') ?? 'Activo') : 'Activo';

        return new FiltroCompetenciasDTO(
            $this->validated('q'),
            $this->validated('tipo'),
            $pedido === 'Todos' ? null : $pedido,
            (int) ($this->validated('page') ?? 1),
            (int) ($this->validated('per_page') ?? 10),
        );
    }
}
