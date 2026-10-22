<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea (con niveles opcionales) o reemplaza (con `version` y los niveles completos) una ruta. Aquí solo se valida la forma;
 * las reglas de negocio (nombre único, puestos activos, orden consecutivo…) las aplica `PreparadorDeRuta`.
 */
class GuardarRutaRequest extends FormRequest
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
        $actualizando = $this->isMethod('PUT');

        return [
            'version' => $actualizando ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'nombre' => ['required', 'string'],
            'tipo' => ['required', 'string'],
            'descripcion' => ['nullable', 'string'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'niveles' => $actualizando ? ['present', 'array'] : ['nullable', 'array'],
            'niveles.*' => ['array'],
            'niveles.*.id' => ['nullable', 'integer', 'min:1'],
            'niveles.*.orden' => ['nullable', 'integer', 'min:1'],
            'niveles.*.puesto_id' => ['required', 'integer', 'min:1'],
            'niveles.*.nombre' => ['nullable', 'string'],
            'niveles.*.experiencia_minima_anios' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión de la ruta.',
            'version.prohibited' => 'La versión solo se envía al actualizar.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto.',
            'tipo.required' => 'Elige el tipo de ruta.',
            'descripcion.string' => 'La descripción debe ser texto.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'departamento_id.min' => 'El departamento no es válido.',
            'niveles.present' => 'Faltan los niveles de la ruta.',
            'niveles.array' => 'Los niveles no son válidos.',
            'niveles.*.array' => 'El nivel no es válido.',
            'niveles.*.puesto_id.required' => 'Elige el puesto del nivel.',
            'niveles.*.puesto_id.integer' => 'El puesto del nivel no es válido.',
            'niveles.*.puesto_id.min' => 'El puesto del nivel no es válido.',
            'niveles.*.orden.integer' => 'El orden del nivel no es válido.',
            'niveles.*.experiencia_minima_anios.numeric' => 'La experiencia mínima debe ser un número de años.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return $this->validated() + ['niveles' => []];
    }
}
