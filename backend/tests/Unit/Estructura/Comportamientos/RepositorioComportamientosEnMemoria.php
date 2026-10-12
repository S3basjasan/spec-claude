<?php

namespace Tests\Unit\Estructura\Comportamientos;

use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaRegistrosComportamientos;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

/**
 * Doble en memoria del repositorio de comportamientos para probar los casos de uso sin base de datos.
 */
class RepositorioComportamientosEnMemoria implements ComportamientoRepositoryInterface
{
    /** @var array<int, CompetenciaAsociada> */
    public array $competencias = [];

    /** @var array<int, RegistroComportamientos> */
    public array $registros = [];

    public ?int $ultimoActor = null;

    public int $escrituras = 0;

    public function agregarCompetencia(string $nombre, EstadoCompetencia $estado = EstadoCompetencia::Activo, TipoCompetencia $tipo = TipoCompetencia::Cardinal): CompetenciaAsociada
    {
        $id = count($this->competencias) + 1;

        return $this->competencias[$id] = new CompetenciaAsociada($id, sprintf('COMP-%04d', $id), $nombre, "Descripción de $nombre", $tipo, $estado);
    }

    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaRegistrosComportamientos
    {
        $items = array_filter($this->registros, fn (RegistroComportamientos $r) => ($q === null || $q === '' || str_contains(mb_strtolower($r->competencia->nombre), mb_strtolower($q)))
            && ($tipo === null || $r->competencia->tipo === $tipo)
            && ($estado === null || $r->competencia->estado === $estado));
        usort($items, fn ($a, $b) => strcmp($a->competencia->nombre, $b->competencia->nombre));

        return new PaginaRegistrosComportamientos(array_slice($items, ($pagina - 1) * $porPagina, $porPagina), count($items));
    }

    public function obtener(int $competenciaId): ?RegistroComportamientos
    {
        return $this->registros[$competenciaId] ?? null;
    }

    public function obtenerPorCompetencias(array $competenciaIds): array
    {
        return array_values(array_intersect_key($this->registros, array_flip($competenciaIds)));
    }

    public function obtenerActivos(): array
    {
        return array_values(array_filter($this->registros, fn (RegistroComportamientos $r) => $r->competenciaActiva()));
    }

    public function disponibles(): array
    {
        return array_values(array_filter(
            $this->competencias,
            fn (CompetenciaAsociada $c) => $c->estado === EstadoCompetencia::Activo && ! isset($this->registros[$c->id]),
        ));
    }

    public function crear(int $competenciaId, array $comportamientos, ?int $actorId): RegistroComportamientos
    {
        $competencia = $this->competencias[$competenciaId] ?? null;
        if ($competencia === null || $competencia->estado !== EstadoCompetencia::Activo) {
            throw new CompetenciaNoDisponible;
        }
        if (isset($this->registros[$competenciaId])) {
            throw new CompetenciaYaTieneRegistro;
        }
        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->registros[$competenciaId] = new RegistroComportamientos($competencia, 1, $actorId, null, $comportamientos);
    }

    public function actualizar(int $competenciaId, int $versionEsperada, array $comportamientos, ?int $actorId): RegistroComportamientos
    {
        $actual = $this->registros[$competenciaId] ?? throw new RegistroNoEncontrado;
        if ($actual->version !== $versionEsperada) {
            throw new RegistroModificado;
        }
        if ($actual->comportamientos === $comportamientos) {
            return $actual;
        }
        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->registros[$competenciaId] = new RegistroComportamientos($actual->competencia, $actual->version + 1, $actual->creadoPor, $actorId, $comportamientos);
    }
}
