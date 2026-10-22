<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Application\DTO\DatosDePublicacionDTO;

class PublicarVersionRequest extends PoliticaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string'],
            'revision' => ['nullable', 'array'],
            'revision.revisor' => ['nullable', 'string'],
            'revision.observaciones' => ['nullable', 'string'],
            'inicio' => ['required', 'array'],
            'inicio.modo' => ['required', 'string'],
            'inicio.fecha_hora_local' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión del borrador.',
            'version.integer' => 'La versión no es válida.',
            'version.min' => 'La versión no es válida.',
            'inicio.required' => 'Elige publicar de inmediato o programar un inicio.',
            'inicio.array' => 'El inicio no es válido.',
            'inicio.modo.required' => 'Elige publicar de inmediato o programar un inicio.',
        ];
    }

    public function datos(): DatosDePublicacionDTO
    {
        $v = $this->validated();

        return new DatosDePublicacionDTO(
            (int) $v['version'],
            (string) ($v['motivo'] ?? ''),
            (string) ($v['revision']['revisor'] ?? ''),
            (string) ($v['revision']['observaciones'] ?? ''),
            (array) $v['inicio'],
            $this->header('Idempotency-Key'),
        );
    }
}
