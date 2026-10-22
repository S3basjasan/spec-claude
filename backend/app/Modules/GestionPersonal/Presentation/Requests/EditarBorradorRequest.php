<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class EditarBorradorRequest extends VersionDeBorradorRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return parent::rules() + [
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'parametros' => ['sometimes', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return parent::messages() + [
            'descripcion.string' => 'El valor no es válido.',
            'parametros.array' => 'Los parámetros no son válidos.',
        ];
    }

    public function descripcion(): ?string
    {
        return $this->has('descripcion') ? (string) ($this->validated('descripcion') ?? '') : null;
    }

    /** @return array<string, mixed>|null */
    public function parametros(): ?array
    {
        return $this->has('parametros') ? (array) $this->validated('parametros') : null;
    }
}
