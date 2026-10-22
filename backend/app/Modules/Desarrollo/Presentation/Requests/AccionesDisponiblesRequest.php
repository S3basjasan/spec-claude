<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccionesDisponiblesRequest extends FormRequest
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
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
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
            'plan_id.integer' => 'El plan no es válido.',
            'q.max' => 'La búsqueda no puede superar los 120 caracteres.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    public function planId(): ?int
    {
        return $this->filled('plan_id') ? (int) $this->validated()['plan_id'] : null;
    }

    public function busqueda(): ?string
    {
        return $this->validated()['q'] ?? null;
    }

    public function pagina(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated()['per_page'] ?? 10);
    }
}
