<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\HistorialDTO;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;

final class ListarHistorial
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly NombresDeUsuarioInterface $nombres,
    ) {}

    /**
     * @throws CompetenciaNoEncontrada
     */
    public function ejecutar(int $competenciaId, int $pagina, int $porPagina): HistorialDTO
    {
        if (! $this->competencias->existe($competenciaId)) {
            throw new CompetenciaNoEncontrada;
        }

        $resultado = $this->repositorio->historialDe($competenciaId, $pagina, $porPagina);
        $usuarios = $this->nombres->nombresDe(array_values(array_filter(array_column($resultado['items'], 'usuario_id'))));

        $items = array_map(fn (array $i) => [
            'fecha' => $i['fecha'],
            'usuario' => $i['usuario_id'] === null ? 'Usuario eliminado' : ($usuarios[$i['usuario_id']] ?? 'Usuario eliminado'),
            'tipo_cambio' => $i['tipo_cambio'],
            'accion' => $i['accion'],
            'recurso' => $i['recurso'],
            'valor_anterior' => $i['valor_anterior'],
            'valor_nuevo' => $i['valor_nuevo'],
        ], $resultado['items']);

        return new HistorialDTO($items, $resultado['total'], $pagina, $porPagina);
    }
}
