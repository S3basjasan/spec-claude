<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base de las solicitudes de políticas: solo valida forma y tipos de entrada; las reglas de negocio viven en el
 * dominio (`ReglasDePolitica`). Los campos desconocidos fuera de `parametros` se ignoran.
 */
abstract class PoliticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validador): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos no son válidos.',
            'code' => 'VALIDACION',
            'errors' => $validador->errors()->toArray(),
        ], 422));
    }
}
