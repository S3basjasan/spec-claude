<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros y paginación del mapa de talento. */
class MapaDeTalentoRequest extends FormRequest
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
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'ruta_id' => ['nullable', 'integer', 'min:1'],
            'preparacion' => ['nullable', 'string', 'in:Lista,EnDesarrollo,NoLista,Incompleta'],
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
            'departamento_id.integer' => 'El departamento no es válido.',
            'ruta_id.integer' => 'La ruta no es válida.',
            'preparacion.in' => 'La preparación no es válida.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 100.',
        ];
    }

    /** @return array{departamento_id: int|null, ruta_id: int|null, preparacion: string|null} */
    public function filtros(): array
    {
        $v = $this->validated();

        return [
            'departamento_id' => isset($v['departamento_id']) ? (int) $v['departamento_id'] : null,
            'ruta_id' => isset($v['ruta_id']) ? (int) $v['ruta_id'] : null,
            'preparacion' => $v['preparacion'] ?? null,
        ];
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
