<?php

namespace App\Modules\Compensaciones\Domain\Repositories;

interface AuditoriaCategoriaRepository
{
    /**
     * Registra una fila por cada campo, con su valor anterior y nuevo. Historial de solo inserción.
     *
     * @param  array<string, array{0: string|null, 1: string|null}>  $cambios  campo => [anterior, nuevo]
     */
    public function registrar(int $categoriaId, array $cambios, ?int $actorId): void;
}
