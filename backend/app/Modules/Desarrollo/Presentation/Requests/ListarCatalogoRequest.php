<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Application\DTO\FiltroCatalogoDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarCatalogoRequest extends FormRequest
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
            'tipo' => ['nullable', 'string', Rule::in(['Cardinal', 'Especifica'])],
            'area_formativa_id' => ['nullable', 'integer', 'min:1'],
            'cobertura' => ['nullable', 'string', Rule::in(['con', 'sin', 'sin_oferta'])],
            'estado' => ['nullable', 'string', Rule::in(['Activo', 'Inactivo', 'Todos'])],
            'competencia' => ['nullable', 'integer', 'min:1'],
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
            'tipo.in' => 'El tipo debe ser Cardinal o Específica.',
            'area_formativa_id.integer' => 'El área formativa no es válida.',
            'cobertura.in' => 'El filtro de cobertura no es válido.',
            'estado.in' => 'El estado debe ser Activo, Inactivo o Todos.',
            'competencia.integer' => 'La competencia no es válida.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    /**
     * Resuelve el estado efectivo según el rol: quien no es administrador solo recibe competencias `Activo`
     * (el parámetro se ignora); el administrador recibe `Activo` por defecto, o `Inactivo`/`Todos` si lo pide.
     */
    public function filtro(bool $esAdministrador, int $usuarioId): FiltroCatalogoDTO
    {
        $pedido = $esAdministrador ? ($this->validated('estado') ?? 'Activo') : 'Activo';

        return new FiltroCatalogoDTO(
            $this->validated('q'),
            $this->validated('tipo'),
            $this->filled('area_formativa_id') ? (int) $this->validated('area_formativa_id') : null,
            $this->validated('cobertura'),
            $pedido === 'Todos' ? null : $pedido,
            $this->filled('competencia') ? (int) $this->validated('competencia') : null,
            (int) ($this->validated('page') ?? 1),
            (int) ($this->validated('per_page') ?? 10),
            $esAdministrador,
            $usuarioId,
        );
    }
}
