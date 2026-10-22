<?php

namespace Tests\Unit\Estructura\Competencias;

use App\Modules\Estructura\Domain\Repositories\EscalaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;

/** Doble en memoria del catálogo de escalas: Letras (activa), Números y Categorías. */
final class EscalasEnMemoria implements EscalaRepositoryInterface
{
    /**
     * @return array<int, EscalaDeGrados>
     */
    public static function catalogo(): array
    {
        return [
            1 => new EscalaDeGrados(1, 'Letras', true, [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D']),
            2 => new EscalaDeGrados(2, 'Números', false, [1 => 'Nivel 1', 2 => 'Nivel 2', 3 => 'Nivel 3', 4 => 'Nivel 4']),
            3 => new EscalaDeGrados(3, 'Categorías', false, [1 => 'Excelente', 2 => 'Muy buena', 3 => 'Buena', 4 => 'Normal']),
        ];
    }

    public function listar(): array
    {
        return array_values(self::catalogo());
    }

    public function obtener(int $id): ?EscalaDeGrados
    {
        return self::catalogo()[$id] ?? null;
    }
}
