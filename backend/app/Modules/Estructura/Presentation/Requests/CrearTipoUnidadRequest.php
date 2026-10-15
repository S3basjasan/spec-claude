<?php

namespace App\Modules\Estructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearTipoUnidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo la forma; la longitud y la unicidad sin tildes ni mayúsculas las valida el repositorio contra el estado vigente.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string'],
            'nivel' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre no es válido.',
            'nivel.prohibited' => 'El nivel lo asigna el sistema: el tipo nuevo se agrega al final de la cadena.',
        ];
    }

    public function nombre(): string
    {
        return trim((string) $this->validated('nombre'));
    }
}
