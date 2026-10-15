<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpcionesDeUnidadRequest extends FormRequest
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
        return ['reubicar' => ['nullable', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reubicar.integer' => 'La unidad a reubicar no es válida.',
            'reubicar.min' => 'La unidad a reubicar no es válida.',
        ];
    }

    public function reubicar(): ?int
    {
        $id = $this->validated('reubicar');

        return $id === null ? null : (int) $id;
    }
}
