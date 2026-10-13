<?php

namespace Tests\Unit\Estructura\Puestos;

use App\Modules\Estructura\Domain\Entities\Puesto;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoModificado;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaConGrados;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\PaginaPuestos;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoFuncion;
use App\Modules\Estructura\Domain\ValueObjects\TipoRequisito;

/**
 * Doble en memoria del repositorio de puestos para probar los casos de uso sin base de datos.
 */
class RepositorioPuestosEnMemoria implements PuestoRepositoryInterface
{
    /** @var array<int, CompetenciaConGrados> */
    public array $competencias = [];

    /** @var array<int, Puesto> */
    public array $puestos = [];

    /** @var array<int, int> Posiciones Ocupadas o Vacantes por id de puesto. */
    public array $posicionesEnUso = [];

    public ?int $ultimoActor = null;

    public int $escrituras = 0;

    public function agregarCompetencia(string $nombre, EstadoCompetencia $estado = EstadoCompetencia::Activo, TipoCompetencia $tipo = TipoCompetencia::Cardinal): CompetenciaConGrados
    {
        $id = count($this->competencias) + 1;
        $asociada = new CompetenciaAsociada($id, sprintf('COMP-%04d', $id), $nombre, "Descripción de $nombre", $tipo, $estado);

        return $this->competencias[$id] = new CompetenciaConGrados($asociada, ['A' => 'Grado A', 'B' => 'Grado B', 'C' => 'Grado C', 'D' => 'Grado D']);
    }

    public function agregarPuesto(string $nombre, EstadoPuesto $estado = EstadoPuesto::Activo, int $competenciaId = 0): Puesto
    {
        $id = count($this->puestos) + 1;
        $competencias = $competenciaId === 0 ? [] : [new CompetenciaDePuesto($this->competencias[$competenciaId]->competencia, 'B')];

        return $this->puestos[$id] = new Puesto(
            $id,
            sprintf('PU-%03d', $id),
            $nombre,
            "Descripción de $nombre",
            "Misión de $nombre",
            $estado,
            1,
            null,
            null,
            [new FuncionPuesto(1, 'Función esencial.', TipoFuncion::Esencial)],
            [new RequisitoPuesto(TipoRequisito::Formacion, 'Título afín.'), new RequisitoPuesto(TipoRequisito::Experiencia, '2 años.')],
            $competencias,
        );
    }

    public function listar(?string $q, ?EstadoPuesto $estado, int $pagina, int $porPagina): PaginaPuestos
    {
        $items = array_filter($this->puestos, fn (Puesto $p) => ($q === null || $q === ''
                || str_contains(mb_strtolower($p->nombre), mb_strtolower($q))
                || str_contains(mb_strtolower($p->codigo), mb_strtolower($q)))
            && ($estado === null || $p->estado === $estado));
        usort($items, fn (Puesto $a, Puesto $b) => strcmp($a->nombre, $b->nombre));

        return new PaginaPuestos(array_slice($items, ($pagina - 1) * $porPagina, $porPagina), count($items));
    }

    public function obtener(int $id): ?Puesto
    {
        return $this->puestos[$id] ?? null;
    }

    public function obtenerPorIds(array $ids): array
    {
        return array_values(array_intersect_key($this->puestos, array_flip($ids)));
    }

    public function obtenerActivos(): array
    {
        return array_values(array_filter($this->puestos, fn (Puesto $p) => $p->activo()));
    }

    public function competenciasDisponibles(): array
    {
        return array_values(array_filter($this->competencias, fn (CompetenciaConGrados $c) => $c->competencia->estado === EstadoCompetencia::Activo));
    }

    public function competenciasPorIds(array $ids): array
    {
        return array_intersect_key($this->competencias, array_flip($ids));
    }

    public function crear(DatosPuesto $datos, ?int $actorId): Puesto
    {
        $this->asegurarNombreLibre($datos->nombre);
        $this->ultimoActor = $actorId;
        $this->escrituras++;
        $id = count($this->puestos) + 1;

        return $this->puestos[$id] = $this->construir($id, $datos, EstadoPuesto::Activo, 1, $actorId, null);
    }

    public function actualizar(int $id, int $versionEsperada, DatosPuesto $datos, ?int $actorId): Puesto
    {
        $actual = $this->puestos[$id] ?? throw new PuestoNoEncontrado;
        $this->asegurarNombreLibre($datos->nombre, $id);

        $nuevo = $this->construir($id, $datos, $actual->estado, $actual->version, $actual->creadoPor, $actual->modificadoPor);
        if ($this->mismoContenido($actual, $nuevo)) {
            return $actual;
        }

        if ($versionEsperada !== $actual->version) {
            throw new PuestoModificado;
        }

        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->puestos[$id] = $this->construir($id, $datos, $actual->estado, $actual->version + 1, $actual->creadoPor, $actorId);
    }

    public function cambiarEstado(int $id, EstadoPuesto $nuevo, ?int $actorId): Puesto
    {
        $actual = $this->puestos[$id] ?? throw new PuestoNoEncontrado;

        if ($actual->estado === $nuevo) {
            return $actual;
        }

        $enUso = $this->posicionesEnUso[$id] ?? 0;
        if ($nuevo === EstadoPuesto::Inactivo && $enUso > 0) {
            throw new PuestoConPosicionesEnUso($enUso);
        }

        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->puestos[$id] = new Puesto(
            $actual->id, $actual->codigo, $actual->nombre, $actual->descripcion, $actual->mision, $nuevo, $actual->version,
            $actual->creadoPor, $actual->modificadoPor, $actual->funciones, $actual->requisitos, $actual->competencias,
        );
    }

    private function asegurarNombreLibre(string $nombre, ?int $propio = null): void
    {
        foreach ($this->puestos as $puesto) {
            if ($puesto->id !== $propio && mb_strtolower(trim($puesto->nombre)) === mb_strtolower(trim($nombre))) {
                throw new NombreDePuestoDuplicado;
            }
        }
    }

    private function construir(int $id, DatosPuesto $datos, EstadoPuesto $estado, int $version, ?int $creadoPor, ?int $modificadoPor): Puesto
    {
        $competencias = array_map(
            fn ($asignacion) => new CompetenciaDePuesto($this->competencias[$asignacion->competenciaId]->competencia, $asignacion->grado),
            array_values($datos->competencias),
        );

        $funciones = [];
        foreach (array_values($datos->funciones) as $indice => $funcion) {
            $funciones[] = new FuncionPuesto($indice + 1, trim($funcion->descripcion), $funcion->tipo);
        }

        return new Puesto(
            $id,
            sprintf('PU-%03d', $id),
            trim($datos->nombre),
            trim($datos->descripcion),
            trim($datos->mision),
            $estado,
            $version,
            $creadoPor,
            $modificadoPor,
            $funciones,
            array_map(fn (RequisitoPuesto $r) => new RequisitoPuesto($r->tipo, trim($r->descripcion)), array_values($datos->requisitos)),
            $competencias,
        );
    }

    private function mismoContenido(Puesto $a, Puesto $b): bool
    {
        return $a->nombre === $b->nombre && $a->descripcion === $b->descripcion && $a->mision === $b->mision
            && $this->plano($a) === $this->plano($b);
    }

    /**
     * @return array<string, mixed>
     */
    private function plano(Puesto $puesto): array
    {
        $requisitos = array_map(fn (RequisitoPuesto $r) => $r->tipo->value.'|'.$r->descripcion, $puesto->requisitos);
        sort($requisitos);
        $competencias = [];
        foreach ($puesto->competencias as $c) {
            $competencias[$c->competencia->id] = $c->gradoRequerido;
        }
        ksort($competencias);

        return [
            'funciones' => array_map(fn (FuncionPuesto $f) => [$f->descripcion, $f->tipo->value], $puesto->funciones),
            'requisitos' => $requisitos,
            'competencias' => $competencias,
        ];
    }
}
