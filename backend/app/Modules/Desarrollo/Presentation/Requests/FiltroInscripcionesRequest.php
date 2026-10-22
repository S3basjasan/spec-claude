<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros y paginación de la lista de inscripciones. */
class FiltroInscripcionesRequest extends FormRequest
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
            'ruta_id' => ['nullable', 'integer', 'min:1'],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'in:Activa,Finalizada,Retirada'],
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
            'ruta_id.integer' => 'La ruta no es válida.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'estado.in' => 'El estado no es válido.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 100.',
        ];
    }

    /** @return array{ruta_id: int|null, colaborador_id: int|null, estado: string|null} */
    public function filtro(): array
    {
        $v = $this->validated();

        return [
            'ruta_id' => isset($v['ruta_id']) ? (int) $v['ruta_id'] : null,
            'colaborador_id' => isset($v['colaborador_id']) ? (int) $v['colaborador_id'] : null,
            'estado' => $v['estado'] ?? null,
        ];
    }

    public function pagina(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated()['per_page'] ?? 20);
    }
}
