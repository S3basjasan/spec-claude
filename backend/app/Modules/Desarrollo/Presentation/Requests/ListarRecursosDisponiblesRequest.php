<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarRecursosDisponiblesRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(['curso', 'microcontenido'])],
            'q' => ['nullable', 'string', 'max:50'],
            'competencia_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Indica si buscas cursos o micro-contenidos.',
            'tipo.in' => 'El tipo de recurso debe ser curso o microcontenido.',
            'q.max' => 'La búsqueda no puede superar los 50 caracteres.',
            'competencia_id.integer' => 'La competencia no es válida.',
        ];
    }

    public function tipoRecurso(): TipoRecurso
    {
        return TipoRecurso::from($this->validated('tipo'));
    }
}
