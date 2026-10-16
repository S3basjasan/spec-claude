<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** Datos de los selectores del paso 1: colaboradores y departamentos evaluables. */
final class OpcionesDnfDTO
{
    /**
     * @param  list<array{id: int, nombre: string, puesto: string, departamento_id: int|null}>  $colaboradores
     * @param  list<array{id: int, nombre: string, colaboradores_evaluables: int}>  $departamentos
     */
    public function __construct(public readonly array $colaboradores, public readonly array $departamentos) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['colaboradores' => $this->colaboradores, 'departamentos' => $this->departamentos];
    }
}
