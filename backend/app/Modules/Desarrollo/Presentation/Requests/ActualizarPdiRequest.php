<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

/** Cuerpo para editar un plan en Borrador: el de la creación más la versión leída. El tipo y el colaborador no cambian. */
class ActualizarPdiRequest extends CrearPdiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reglas = parent::rules();
        $reglas['colaborador_id'] = ['nullable', 'integer', 'min:1'];
        $reglas['tipo'] = ['nullable', 'string'];

        return $reglas + ['version' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + ['version.required' => 'Falta la versión del plan.'];
    }
}
