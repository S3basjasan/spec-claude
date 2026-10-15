<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;
use Illuminate\Foundation\Http\FormRequest;

class GuardarUnidadRequest extends FormRequest
{
    public const MAX_NOMBRE = 150;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo valida la forma. Que la unidad padre exista, esté activa y admita hijas, que sea del tipo inmediatamente
     * superior y que no haya ciclos lo comprueba el repositorio contra el estado vigente, dentro de la transacción de
     * escritura. El tipo lo calcula el backend: nunca viene del cliente.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.self::MAX_NOMBRE],
            // Al crear la unidad padre es obligatoria; al editar solo la raíz la conserva nula (lo valida el repositorio).
            'unidad_padre_id' => $this->isMethod('POST') ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:1'],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
            // El código lo asigna el sistema; en PUT se acepta solo si coincide con el actual (lo comprueba el caso de uso).
            'codigo' => ['nullable', 'string', 'max:20'],
            // El tipo, el estado y las fechas nunca vienen del cliente.
            'tipo_unidad_id' => ['prohibited'],
            'estado' => ['prohibited'],
            'fecha_creacion' => ['prohibited'],
            'fecha_supresion' => ['prohibited'],
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
            'nombre.max' => 'El nombre no puede superar los '.self::MAX_NOMBRE.' caracteres.',
            'unidad_padre_id.required' => 'Selecciona la unidad padre.',
            'unidad_padre_id.integer' => 'Selecciona una unidad padre válida.',
            'unidad_padre_id.min' => 'Selecciona una unidad padre válida.',
            'version.required' => 'Falta la versión de la unidad. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión de la unidad no es válida.',
            'version.min' => 'La versión de la unidad no es válida.',
            'tipo_unidad_id.prohibited' => 'El tipo de unidad lo calcula el sistema a partir de la unidad padre.',
            'estado.prohibited' => 'El estado no se puede indicar: cambia solo con Desactivar o Reactivar.',
            'fecha_creacion.prohibited' => 'La fecha de creación la asigna el sistema.',
            'fecha_supresion.prohibited' => 'La fecha de supresión la asigna el sistema.',
        ];
    }

    public function datos(): DatosUnidad
    {
        $padre = $this->validated('unidad_padre_id');

        return new DatosUnidad(trim((string) $this->validated('nombre')), $padre === null ? null : (int) $padre);
    }

    public function codigoEnviado(): ?string
    {
        $codigo = $this->validated('codigo');

        return $codigo === null ? null : (string) $codigo;
    }
}
