<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Application\DTO\CargaDeDocumentoDTO;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Carga multipart. Solo valida forma y tipos: las reglas de negocio y la validación del contenido viven en el caso de
 * uso. Los metadatos (formato, tamaño, actor, clave de almacenamiento) los fija el servidor; `tenant_id`, `created_by`
 * y `object_key` del cuerpo se ignoran.
 */
class CargarDocumentoRequest extends FormRequest
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
            'titulo' => ['required', 'string'],
            'categoria_id' => ['required', 'integer', 'min:1'],
            'archivo' => ['required', 'file'],
            'visible_colaborador' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Este campo es obligatorio.',
            'titulo.string' => 'El valor no es válido.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'categoria_id.integer' => 'La categoría no es válida.',
            'categoria_id.min' => 'La categoría no es válida.',
            'archivo.required' => 'Selecciona un archivo PDF.',
            'archivo.file' => 'El archivo no se pudo recibir. Selecciónalo de nuevo.',
            'visible_colaborador.boolean' => 'El valor no es válido.',
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

    public function carga(): CargaDeDocumentoDTO
    {
        $archivo = $this->file('archivo');

        return new CargaDeDocumentoDTO(
            $this->validated('titulo'),
            (int) $this->validated('categoria_id'),
            $this->boolean('visible_colaborador'),
            (string) $archivo?->getRealPath(),
        );
    }
}
