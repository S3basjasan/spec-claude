<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/** Formalización de una salida: solo se aceptan los campos declarados; el contenido de negocio se valida en el caso de uso. */
class FormalizarSalidaRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['colaborador_id', 'motivo', 'fecha_efectiva', 'documento_ids'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'colaborador_id' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:20'],
            'fecha_efectiva' => ['nullable', 'string', 'max:10'],
            'documento_ids' => ['nullable', 'array', 'max:50'],
        ];
    }

    public function colaboradorId(): int
    {
        return (int) $this->validated('colaborador_id');
    }

    /** @return list<mixed> */
    public function documentoIds(): array
    {
        return array_values((array) ($this->validated('documento_ids') ?? []));
    }
}
