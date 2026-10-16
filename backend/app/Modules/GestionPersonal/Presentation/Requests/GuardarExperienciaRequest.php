<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class GuardarExperienciaRequest extends GuardarDesarrolloRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'organizacion' => $this->texto(true),
            'cargo' => $this->texto(true),
            'fecha_inicio' => $this->texto(true),
            'fecha_finalizacion' => ['sometimes', 'nullable', 'string'],
            'funciones' => ['sometimes', 'nullable', 'string'],
            'observaciones' => ['sometimes', 'nullable', 'string'],
        ] + $this->reglaDeVersion();
    }
}
