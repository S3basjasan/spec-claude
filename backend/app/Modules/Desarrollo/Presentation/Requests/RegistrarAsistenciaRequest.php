<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La forma de cada registro se valida aquí; las reglas de negocio (inscritos, calificación solo con Presente,
 * observaciones con asistencia registrada) las aplica el dominio para devolver todos los errores juntos.
 */
class RegistrarAsistenciaRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'registros' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión del registro; recarga la página.',
            'version.integer' => 'La versión del registro no es válida.',
            'registros.required' => 'Debes enviar al menos un registro.',
            'registros.array' => 'Los registros deben ser una lista.',
            'registros.min' => 'Debes enviar al menos un registro.',
        ];
    }

    public function version(): int
    {
        return (int) $this->validated()['version'];
    }

    /**
     * Registros tal como llegan, con el tipo original de cada campo (el dominio los valida).
     *
     * @return list<array<string, mixed>>
     */
    public function registros(): array
    {
        return array_values(array_map(
            fn ($r) => is_array($r) ? $r : [],
            $this->validated()['registros'],
        ));
    }
}
