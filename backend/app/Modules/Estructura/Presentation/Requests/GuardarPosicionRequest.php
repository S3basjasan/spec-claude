<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\DatosPosicion;
use App\Modules\Estructura\Domain\ValueObjects\Modalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPosicionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo valida la forma. Que el puesto, la unidad y el supervisor existan y estén activos o no Suprimidos, que no haya
     * ciclos y que exista una sola máxima autoridad lo comprueba el repositorio contra el estado vigente, dentro de la
     * transacción de escritura. El estado, el código y el ocupante nunca vienen del cliente.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // El puesto se elige al crear y no cambia después.
            'puesto_id' => $this->isMethod('POST') ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'unidad_organizacional_id' => ['required', 'integer', 'min:1'],
            // Nulo solo en la máxima autoridad (lo valida el repositorio).
            'posicion_jefe_id' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['required', 'string', Rule::enum(Modalidad::class)],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
            'codigo' => ['prohibited'],
            'user_id' => ['prohibited'],
            'estado' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'puesto_id.required' => 'Selecciona el puesto.',
            'puesto_id.integer' => 'Selecciona un puesto válido.',
            'puesto_id.min' => 'Selecciona un puesto válido.',
            'puesto_id.prohibited' => 'El puesto de una posición no se puede cambiar.',
            'unidad_organizacional_id.required' => 'Selecciona la unidad organizacional.',
            'unidad_organizacional_id.integer' => 'Selecciona una unidad organizacional válida.',
            'unidad_organizacional_id.min' => 'Selecciona una unidad organizacional válida.',
            'posicion_jefe_id.integer' => 'Selecciona un supervisor válido.',
            'posicion_jefe_id.min' => 'Selecciona un supervisor válido.',
            'modalidad.required' => 'Selecciona la modalidad.',
            'modalidad.enum' => 'Selecciona una modalidad válida.',
            'version.required' => 'Falta la versión de la posición. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión de la posición no es válida.',
            'version.min' => 'La versión de la posición no es válida.',
            'codigo.prohibited' => 'El código lo asigna el sistema y no se puede cambiar.',
            'user_id.prohibited' => 'El ocupante no se puede indicar desde aquí.',
            'estado.prohibited' => 'El estado no se puede indicar: una posición nueva nace Vacante y solo cambia con Suprimir.',
        ];
    }

    public function datos(): DatosPosicion
    {
        $puesto = $this->validated('puesto_id');
        $supervisor = $this->validated('posicion_jefe_id');

        return new DatosPosicion(
            $puesto === null ? null : (int) $puesto,
            (int) $this->validated('unidad_organizacional_id'),
            $supervisor === null ? null : (int) $supervisor,
            Modalidad::from((string) $this->validated('modalidad')),
        );
    }
}
