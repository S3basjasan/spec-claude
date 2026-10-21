<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;

interface EscalaRepositoryInterface
{
    /**
     * Las escalas del catálogo con sus cuatro valores ordenados y la marca de la activa.
     *
     * @return array<int, EscalaDeGrados>
     */
    public function listar(): array;

    public function obtener(int $id): ?EscalaDeGrados;
}
