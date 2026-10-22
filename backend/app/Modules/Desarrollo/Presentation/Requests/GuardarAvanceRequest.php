<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarAvanceRequest extends FormRequest
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
        return ['posicion_segundos' => ['nullable', 'integer', 'min:0', 'max:1000000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'posicion_segundos.integer' => 'La posición debe ser un número entero de segundos.',
            'posicion_segundos.min' => 'La posición no puede ser negativa.',
        ];
    }

    public function posicion(): ?int
    {
        $posicion = $this->validated('posicion_segundos');

        return $posicion === null ? null : (int) $posicion;
    }
}
