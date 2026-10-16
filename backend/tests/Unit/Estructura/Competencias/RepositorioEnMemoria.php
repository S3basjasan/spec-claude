<?php

namespace Tests\Unit\Estructura\Competencias;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaCompetencias;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

/** Doble en memoria del repositorio para probar los casos de uso sin base de datos. */
final class RepositorioEnMemoria implements CompetenciaRepositoryInterface
{
    /** @var array<int, Competencia> */
    public array $competencias = [];

    public int $siguienteId = 1;

    public ?string $ultimoQ = null;

    public ?TipoCompetencia $ultimoTipo = null;

    public ?EstadoCompetencia $ultimoEstado = null;

    public ?int $ultimoActor = null;

    /** @var array<int, array{0: int, 1: string, 2: string, 3: ?int}> */
    public array $historial = [];

    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaCompetencias
    {
        $this->ultimoQ = $q;
        $this->ultimoTipo = $tipo;
        $this->ultimoEstado = $estado;
        $items = array_values($this->competencias);

        return new PaginaCompetencias(array_slice($items, ($pagina - 1) * $porPagina, $porPagina), count($items));
    }

    public function obtener(int $id): ?Competencia
    {
        return $this->competencias[$id] ?? null;
    }

    public function obtenerPorIds(array $ids): array
    {
        return array_values(array_intersect_key($this->competencias, array_flip($ids)));
    }

    public function obtenerActivas(): array
    {
        return array_values(array_filter($this->competencias, fn (Competencia $c) => $c->estaActiva()));
    }

    public function obtenerTodas(): array
    {
        return array_values($this->competencias);
    }

    public function crear(string $nombre, string $descripcion, TipoCompetencia $tipo, array $grados, ?int $actorId): Competencia
    {
        foreach ($this->competencias as $existente) {
            if (mb_strtolower($existente->nombre) === mb_strtolower($nombre)) {
                throw new NombreDuplicado;
            }
        }

        $this->ultimoActor = $actorId;
        $id = $this->siguienteId++;

        return $this->competencias[$id] = new Competencia(
            $id, sprintf('COMP-%04d', $id), $nombre, $descripcion, $tipo, EstadoCompetencia::Activo, 1, $actorId, null, $grados,
        );
    }

    public function actualizar(int $id, int $versionEsperada, string $nombre, string $descripcion, TipoCompetencia $tipo, array $grados, ?int $actorId): Competencia
    {
        $actual = $this->competencias[$id] ?? throw new CompetenciaNoEncontrada;

        if ($actual->version !== $versionEsperada) {
            throw new ConflictoDeVersion;
        }

        $this->ultimoActor = $actorId;

        return $this->competencias[$id] = new Competencia(
            $id, $actual->codigo, $nombre, $descripcion, $tipo, $actual->estado, $actual->version + 1, $actual->creadoPor, $actorId, $grados,
        );
    }

    public function cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia
    {
        $actual = $this->competencias[$id] ?? throw new CompetenciaNoEncontrada;

        if ($actual->estado === $nuevo) {
            throw new EstadoSinCambio;
        }

        $this->historial[] = [$id, $actual->estado->value, $nuevo->value, $actorId];

        return $this->competencias[$id] = new Competencia(
            $id, $actual->codigo, $actual->nombre, $actual->descripcion, $actual->tipo, $nuevo, $actual->version, $actual->creadoPor, $actual->modificadoPor, $actual->grados,
        );
    }
}
