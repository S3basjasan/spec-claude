<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Cambio de estado. El valor del estado se valida en ReglasDeCategoria; aquí solo forma y versión.
 */
class CambiarEstadoCategoriaRequest extends FormRequest
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
            'estado' => ['required', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.string' => 'El estado no es válido.',
            'version.required' => 'Falta la versión de la categoría.',
            'version.integer' => 'La versión no es válida.',
            'version.min' => 'La versión no es válida.',
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

    public function estado(): string
    {
        return (string) $this->validated('estado');
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }
}
