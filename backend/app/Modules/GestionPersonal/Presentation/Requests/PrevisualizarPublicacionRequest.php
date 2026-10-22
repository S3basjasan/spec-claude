<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

class PrevisualizarPublicacionRequest extends PoliticaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'inicio' => ['sometimes', 'nullable', 'array'],
            'inicio.modo' => ['required_with:inicio', 'string'],
            'inicio.fecha_hora_local' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function inicio(): ?array
    {
        $inicio = $this->validated('inicio');

        return is_array($inicio) ? $inicio : null;
    }
}
