<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Application\DTO\FiltroColaboradoresDTO;
use Illuminate\Foundation\Http\FormRequest;

class ListarColaboradoresRequest extends FormRequest
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
            'estado' => ['nullable', 'string', 'max:40'],
            'unidad_id' => ['nullable', 'integer', 'min:1'],
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
            'unidad_id.integer' => 'La unidad organizacional no es válida.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    public function filtro(): FiltroColaboradoresDTO
    {
        return new FiltroColaboradoresDTO(
            $this->validated('q'),
            $this->validated('estado'),
            $this->validated('unidad_id') === null ? null : (int) $this->validated('unidad_id'),
            (int) ($this->validated('page') ?? 1),
            (int) ($this->validated('per_page') ?? 20),
        );
    }
}
