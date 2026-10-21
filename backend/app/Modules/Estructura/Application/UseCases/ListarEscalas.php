<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Domain\Repositories\EscalaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;

final class ListarEscalas
{
    public function __construct(private readonly EscalaRepositoryInterface $escalas) {}

    /**
     * Las escalas del catálogo con sus cuatro valores ordenados; el orden 1 es el grado más alto.
     *
     * @return array<int, array{id: int, nombre: string, activo: bool, valores: array<int, array{orden: int, valor: string, etiqueta: string}>}>
     */
    public function ejecutar(): array
    {
        return array_map(fn (EscalaDeGrados $escala) => [
            'id' => $escala->id,
            'nombre' => $escala->nombre,
            'activo' => $escala->activa,
            'valores' => array_map(
                fn (int $orden) => ['orden' => $orden, 'valor' => $escala->valores[$orden], 'etiqueta' => $escala->etiqueta($orden)],
                array_keys($escala->valores),
            ),
        ], $this->escalas->listar());
    }
}
