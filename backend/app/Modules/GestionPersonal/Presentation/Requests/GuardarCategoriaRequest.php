<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Alta (POST) y corrección (PATCH) del nombre. Solo valida forma y tipos: las reglas de negocio viven en
 * ReglasDeCategoria. Los campos desconocidos (`tenant_id`, `created_by`, `estado`…) se ignoran.
 */
class GuardarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function esAlta(): bool
    {
        return $this->isMethod('post');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reglas = ['nombre' => $this->esAlta() ? ['required', 'string'] : ['sometimes', 'required', 'string']];

        if (! $this->esAlta()) {
            $reglas['version'] = ['required', 'integer', 'min:1'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Este campo es obligatorio.',
            'nombre.string' => 'El valor no es válido.',
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

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $validados = $this->validated();
        unset($validados['version']);

        return $validados;
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }
}
