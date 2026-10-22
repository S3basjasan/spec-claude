<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros y paginación de la lista de rutas. */
class FiltroRutasRequest extends FormRequest
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
            'tipo' => ['nullable', 'string', 'in:Especialista,Gerencial,Interareas,JovenesProfesionales'],
            'estado' => ['nullable', 'string', 'in:Borrador,Vigente,Archivada'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo de ruta no es válido.',
            'estado.in' => 'El estado no es válido.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'q.max' => 'La búsqueda no puede superar los 80 caracteres.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 100.',
        ];
    }

    /** @return array{tipo: string|null, estado: string|null, departamento_id: int|null, q: string|null} */
    public function filtro(): array
    {
        $v = $this->validated();

        return [
            'tipo' => $v['tipo'] ?? null,
            'estado' => $v['estado'] ?? null,
            'departamento_id' => isset($v['departamento_id']) ? (int) $v['departamento_id'] : null,
            'q' => $v['q'] ?? null,
        ];
    }

    public function pagina(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated()['per_page'] ?? 50);
    }
}
