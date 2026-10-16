<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpcionesDePosicionRequest extends FormRequest
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
        return ['excluir' => ['nullable', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'excluir.integer' => 'La posición a excluir no es válida.',
            'excluir.min' => 'La posición a excluir no es válida.',
        ];
    }

    public function excluir(): ?int
    {
        $id = $this->validated('excluir');

        return $id === null ? null : (int) $id;
    }
}
