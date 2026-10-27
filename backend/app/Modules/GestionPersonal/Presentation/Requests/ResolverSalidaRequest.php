<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/** Cambio sobre un expediente existente (cancelar, completar tarea, aplicar, cerrar, entrevista): siempre con la versión vista. */
class ResolverSalidaRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['version', 'razon', 'documento_ids', 'motivo'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'razon' => ['nullable', 'string', 'max:1100'],
            'documento_ids' => ['nullable', 'array', 'max:50'],
            'motivo' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }

    /** @return list<mixed> */
    public function documentoIds(): array
    {
        return array_values((array) ($this->validated('documento_ids') ?? []));
    }
}
