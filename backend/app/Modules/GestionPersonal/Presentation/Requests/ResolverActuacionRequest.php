<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/** Cambio de estado de una actuación existente: siempre con la versión que el cliente vio. */
class ResolverActuacionRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['version', 'justificacion', 'reconoce_cruce', 'resultado', 'observacion'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'justificacion' => ['nullable', 'string', 'max:1100'],
            'reconoce_cruce' => ['nullable', 'boolean'],
            'resultado' => ['nullable', 'string', 'max:20'],
            'observacion' => ['nullable', 'string', 'max:600'],
        ];
    }

    public function version(): int
    {
        return (int) $this->input('version');
    }
}
