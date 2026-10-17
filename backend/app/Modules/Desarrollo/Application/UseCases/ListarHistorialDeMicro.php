<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;

/** Historial de estados de un micro-contenido, más reciente primero. */
final class ListarHistorialDeMicro
{
    public function __construct(
        private readonly MicrocontenidoRepositoryInterface $repositorio,
        private readonly NombresDeUsuarioInterface $nombres,
    ) {}

    /**
     * @return array{items: array<int, array{id: int, estado_anterior: string|null, estado_nuevo: string, usuario: string|null, fecha: string|null}>, total: int}
     */
    public function ejecutar(int $id, int $pagina, int $porPagina): array
    {
        if ($this->repositorio->obtener($id) === null) {
            throw new MicroNoEncontrado;
        }

        $resultado = $this->repositorio->historial($id, $pagina, $porPagina);
        $nombres = $this->nombres->nombresDe(array_values(array_filter(array_column($resultado['items'], 'user_id'), fn ($v) => $v !== null)));

        return [
            'items' => array_map(fn (array $h) => [
                'id' => $h['id'],
                'estado_anterior' => $h['estado_anterior'],
                'estado_nuevo' => $h['estado_nuevo'],
                'usuario' => $h['user_id'] === null ? null : ($nombres[$h['user_id']] ?? null),
                'fecha' => $h['fecha'],
            ], $resultado['items']),
            'total' => $resultado['total'],
        ];
    }
}
