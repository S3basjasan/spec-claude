<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/** Consultas del Administrador que parten de una ficha: contexto de movimiento y documentos referenciables. */
class ContextoDeColaboradorRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['colaborador_id', 'q', 'page', 'per_page'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'colaborador_id' => ['required', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function colaboradorId(): int
    {
        return (int) $this->validated('colaborador_id');
    }

    public function texto(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }

    public function pagina(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
