<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class GuardarFormacionRequest extends GuardarDesarrolloRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nivel' => $this->texto(true),
            'titulo_programa' => $this->texto(true),
            'institucion' => $this->texto(true),
            'estado' => $this->texto(true),
            'fecha_inicio' => ['sometimes', 'nullable', 'string'],
            'fecha_finalizacion' => ['sometimes', 'nullable', 'string'],
            'observaciones' => ['sometimes', 'nullable', 'string'],
        ] + $this->reglaDeVersion();
    }
}
