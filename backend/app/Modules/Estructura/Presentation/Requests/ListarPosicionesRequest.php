<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\FiltroDePosiciones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarPosicionesRequest extends FormRequest
{
    public const MAX_POR_PAGINA = 50;

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
            'estado' => ['nullable', 'string', Rule::in(['Vigentes', 'Ocupada', 'Vacante', 'Suprimida', 'Todas'])],
            'unidad_organizacional_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_POR_PAGINA],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar los 50 caracteres.',
            'estado.in' => 'El estado debe ser Vigentes, Ocupada, Vacante, Suprimida o Todas.',
            'unidad_organizacional_id.integer' => 'La unidad organizacional no es válida.',
            'unidad_organizacional_id.min' => 'La unidad organizacional no es válida.',
            'page.integer' => 'La página no es válida.',
            'page.min' => 'La página no es válida.',
            'per_page.integer' => 'El tamaño de página no es válido.',
            'per_page.min' => 'El tamaño de página no es válido.',
            'per_page.max' => 'El tamaño de página no puede superar '.self::MAX_POR_PAGINA.'.',
        ];
    }

    /**
     * Resuelve el estado efectivo según el rol: quien no es administrador solo recibe Ocupadas y Vacantes (`Suprimida`
     * y `Todas` se tratan como `Vigentes`); el administrador recibe `Vigentes` por defecto o lo que pida.
     */
    public function filtro(bool $esAdministrador): FiltroDePosiciones
    {
        $pedido = (string) ($this->validated('estado') ?? FiltroDePosiciones::VIGENTES);
        $estado = $esAdministrador || in_array($pedido, ['Ocupada', 'Vacante'], true) ? $pedido : FiltroDePosiciones::VIGENTES;
        $unidad = $this->validated('unidad_organizacional_id');

        return new FiltroDePosiciones(
            $this->validated('q'),
            $estado,
            $unidad === null ? null : (int) $unidad,
            (int) ($this->validated('page') ?? 1),
            (int) ($this->validated('per_page') ?? FiltroDePosiciones::POR_PAGINA),
        );
    }
}
