<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarHistorialMicroRequest extends FormRequest
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
            'page.integer' => 'La página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    public function pagina(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
