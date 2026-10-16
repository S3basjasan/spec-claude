<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ArchivarDocumentoRequest extends FormRequest
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
        return ['version' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión del documento.',
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

    public function version(): int
    {
        return (int) $this->validated('version');
    }
}
