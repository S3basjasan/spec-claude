<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class RetirarVersionRequest extends PoliticaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['justificacion' => ['nullable', 'string']];
    }

    public function justificacion(): string
    {
        return (string) ($this->validated('justificacion') ?? '');
    }
}
