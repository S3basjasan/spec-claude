<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Application\DTO\FiltroDocumentosDTO;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ListarDocumentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La consulta propia no admite `estado`: solo ve documentos Disponibles (la regla del caso de uso lo fuerza).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', Rule::in(['Disponible', 'Archivado'])],
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
            'categoria_id.integer' => 'La categoría no es válida.',
            'estado.in' => 'El estado debe ser Disponible o Archivado.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    protected function failedValidation(Validator $validador): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos no son válidos.',
            'code' => 'VALIDACION',
            'errors' => $validador->errors()->toArray(),
        ], 422));
    }

    public function filtro(): FiltroDocumentosDTO
    {
        $q = $this->validated('q');

        return new FiltroDocumentosDTO(
            $q === null || trim((string) $q) === '' ? null : trim((string) $q),
            $this->validated('categoria_id') === null ? null : (int) $this->validated('categoria_id'),
            (string) ($this->validated('estado') ?? 'Disponible'),
            (int) ($this->validated('page') ?? 1),
            (int) ($this->validated('per_page') ?? 20),
        );
    }
}
