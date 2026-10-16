<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\FilaCatalogoDTO;
use App\Modules\Desarrollo\Application\DTO\FiltroCatalogoDTO;
use App\Modules\Desarrollo\Application\DTO\ListadoCatalogoDTO;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Domain\Entities\ConfiguracionFormativa;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lista las competencias con su oferta formativa. Quien no es administrador solo recibe competencias activas,
 * cuenta únicamente recursos disponibles y ve su propio nivel actual.
 */
final class ListarCatalogo
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly NivelesDeColaboradorInterface $niveles,
    ) {}

    public function ejecutar(FiltroCatalogoDTO $filtro): ListadoCatalogoDTO
    {
        $busqueda = $filtro->q === null ? '' : $this->texto($filtro->q);

        $candidatas = array_values(array_filter($this->competencias->todas(), function (array $c) use ($filtro, $busqueda) {
            $estado = $filtro->esAdmin ? $filtro->estado : 'Activo';

            return ($estado === null || $c['estado'] === $estado)
                && ($filtro->competenciaId === null || $c['id'] === $filtro->competenciaId)
                && ($filtro->tipo === null || $c['tipo'] === $filtro->tipo)
                && ($busqueda === '' || str_contains($this->texto($c['nombre']), $busqueda));
        }));

        $configuraciones = $this->repositorio->configuracionesDe(array_column($candidatas, 'id'));

        $filas = [];
        foreach ($candidatas as $c) {
            $config = $configuraciones[$c['id']];
            if ($this->cumple($filtro, $config)) {
                $filas[] = [$c, $config];
            }
        }

        $total = count($filas);
        $pagina = array_slice($filas, ($filtro->pagina - 1) * $filtro->porPagina, $filtro->porPagina);

        $niveles = $filtro->esAdmin ? [] : $this->nivelesDe($filtro->usuarioId);

        return new ListadoCatalogoDTO(
            array_map(fn (array $par) => new FilaCatalogoDTO(
                $par[0]['id'],
                $par[0]['codigo'],
                $par[0]['nombre'],
                $par[0]['tipo'],
                $par[0]['estado'],
                $par[1]->areaFormativaId,
                $par[1]->areaNombre,
                count($par[1]->cursosDisponibles()),
                count($par[1]->microcontenidosDisponibles()),
                $par[1]->recursosDisponibles() >= 1,
                $niveles[$par[0]['id']] ?? null,
                ! $filtro->esAdmin,
            ), $pagina),
            $total,
            $filtro->pagina,
            $filtro->porPagina,
        );
    }

    private function cumple(FiltroCatalogoDTO $filtro, ConfiguracionFormativa $config): bool
    {
        if ($filtro->areaFormativaId !== null && $config->areaFormativaId !== $filtro->areaFormativaId) {
            return false;
        }

        return match ($filtro->cobertura) {
            'con' => count($config->cursosDisponibles()) >= 1,
            'sin' => count($config->cursosDisponibles()) === 0,
            'sin_oferta' => $config->recursosDisponibles() === 0,
            default => true,
        };
    }

    /**
     * @return array<int, string>
     */
    private function nivelesDe(int $usuarioId): array
    {
        try {
            return $this->niveles->nivelesDe($usuarioId);
        } catch (Throwable) {
            return [];
        }
    }

    private function texto(string $valor): string
    {
        return mb_strtolower(Str::ascii(trim($valor)));
    }
}
