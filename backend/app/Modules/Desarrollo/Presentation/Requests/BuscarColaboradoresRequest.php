<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Búsqueda de colaboradores inscribibles. */
class BuscarColaboradoresRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
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
            'q.max' => 'La búsqueda no puede superar los 100 caracteres.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 100.',
        ];
    }

    public function texto(): ?string
    {
        $q = trim((string) ($this->validated()['q'] ?? ''));

        return $q === '' ? null : $q;
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
