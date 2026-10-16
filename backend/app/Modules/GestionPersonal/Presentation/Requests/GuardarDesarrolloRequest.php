<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base de las solicitudes de desarrollo: solo valida forma y tipos; las reglas de negocio viven en ReglasDeDesarrollo.
 * Los campos desconocidos (`colaborador_id`, `tenant_id`, `created_by`…) se ignoran: el destino lo fija la ruta.
 */
abstract class GuardarDesarrolloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function esAlta(): bool
    {
        return $this->isMethod('post');
    }

    /**
     * Texto opcional (nullable) que en PATCH puede omitirse.
     *
     * @return list<string>
     */
    protected function texto(bool $obligatorio): array
    {
        return $obligatorio && $this->esAlta()
            ? ['required', 'string']
            : ['sometimes', 'nullable', 'string'];
    }

    /** @return array<string, array<int, mixed>> */
    protected function reglaDeVersion(): array
    {
        return $this->esAlta() ? [] : ['version' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = ['version.required' => 'Falta la versión del registro.', 'version.integer' => 'La versión no es válida.', 'version.min' => 'La versión no es válida.'];
        foreach (array_keys($this->rules()) as $campo) {
            $mensajes["$campo.required"] = 'Este campo es obligatorio.';
            $mensajes["$campo.string"] = 'El valor no es válido.';
        }

        return $mensajes;
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
     * Campos editables enviados (en PATCH, solo los presentes).
     *
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
