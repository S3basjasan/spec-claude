<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/** Solo el control de edición (`version`) del borrador: descarte. */
class VersionDeBorradorRequest extends PoliticaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión del borrador.',
            'version.integer' => 'La versión no es válida.',
            'version.min' => 'La versión no es válida.',
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }
}
