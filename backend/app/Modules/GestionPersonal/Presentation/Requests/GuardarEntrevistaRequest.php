<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/**
 * Avance de la entrevista de salida. `version` es la de la entrevista; el contenido (identificadores de pregunta, escala
 * de 1 a 5, categorías del catálogo y longitud de los textos) lo valida el cuestionario en el dominio.
 */
class GuardarEntrevistaRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['version', 'valoraciones', 'categorias', 'abiertas'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'valoraciones' => ['nullable', 'array', 'max:20'],
            'categorias' => ['nullable', 'array', 'max:20'],
            'abiertas' => ['nullable', 'array', 'max:20'],
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }

    /** @return array<mixed> */
    public function valoraciones(): array
    {
        return (array) ($this->validated('valoraciones') ?? []);
    }

    /** @return array<mixed> */
    public function categorias(): array
    {
        return array_values((array) ($this->validated('categorias') ?? []));
    }

    /** @return array<mixed> */
    public function abiertas(): array
    {
        return (array) ($this->validated('abiertas') ?? []);
    }
}
