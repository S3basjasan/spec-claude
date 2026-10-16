<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class GuardarMovilidadRequest extends GuardarDesarrolloRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'estado' => ['sometimes', 'required', 'string'],
            'observaciones' => ['sometimes', 'nullable', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
