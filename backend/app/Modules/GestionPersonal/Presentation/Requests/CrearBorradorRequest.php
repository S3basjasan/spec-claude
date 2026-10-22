<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class CrearBorradorRequest extends PoliticaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['base_version_id' => ['nullable', 'integer', 'min:1']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['base_version_id.integer' => 'La versión base no es válida.', 'base_version_id.min' => 'La versión base no es válida.'];
    }

    public function baseVersionId(): ?int
    {
        $base = $this->validated('base_version_id');

        return $base === null ? null : (int) $base;
    }
}
