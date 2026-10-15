<?php

namespace Tests\Unit\Estructura\Unidades;

use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Exceptions\UnidadConDependenciasEnUso;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadModificada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ArbolDeUnidades;
use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\OpcionesDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\UnidadResumida;

/**
 * Doble en memoria del repositorio de unidades para probar los casos de uso sin base de datos. Aplica las mismas
 * reglas que el repositorio real (árbol, raíz única, estado, versión e historial).
 */
class RepositorioUnidadesEnMemoria implements UnidadRepositoryInterface
{
    /** @var array<int, TipoDeUnidad> */
    public array $tiposDeUnidad = [];

    /** @var array<int, array<string, mixed>> */
    public array $filas = [];

    /** @var array<int, int> unidad => posiciones Ocupadas o Vacantes */
    public array $posicionesVigentes = [];

    /** @var array<int, array{unidad: int, campo: string, anterior: ?string, nuevo: ?string, actor: ?int}> */
    public array $historial = [];

    public ?int $ultimoActor = null;

    public int $escrituras = 0;

    public function __construct(private readonly ComparadorDeCambiosDeUnidad $comparador = new ComparadorDeCambiosDeUnidad)
    {
        foreach (['Gerencia', 'Departamento', 'Área', 'Sección'] as $indice => $nombre) {
            $this->tiposDeUnidad[$indice + 1] = new TipoDeUnidad($indice + 1, $nombre);
        }
    }

    public function agregar(string $nombre, ?int $padreId = null, int $tipoId = 2, EstadoUnidad $estado = EstadoUnidad::Activa): int
    {
        $id = count($this->filas) + 1;
        $this->filas[$id] = [
            'nombre' => $nombre, 'tipo' => $tipoId, 'padre' => $padreId, 'estado' => $estado, 'version' => 1,
            'fechaSupresion' => $estado === EstadoUnidad::Inactiva ? '2026-10-14' : null,
            'creadoPor' => null, 'modificadoPor' => null,
        ];

        return $id;
    }

    public function todas(): array
    {
        return array_values($this->construir()['entidades']);
    }

    public function obtener(int $id): ?Unidad
    {
        return $this->construir()['entidades'][$id] ?? null;
    }

    public function obtenerPorIds(array $ids): array
    {
        return array_values(array_filter($this->construir()['entidades'], fn (Unidad $u) => in_array($u->id, $ids, true)));
    }

    public function tipos(): array
    {
        return array_values($this->tiposDeUnidad);
    }

    public function opciones(?int $excluir): OpcionesDeUnidad
    {
        ['entidades' => $entidades, 'arbol' => $arbol] = $this->construir();
        $fuera = $excluir === null ? [] : [$excluir, ...$arbol->descendientesDe($excluir)];

        return new OpcionesDeUnidad(
            $this->tipos(),
            array_values(array_filter($entidades, fn (Unidad $u) => $u->estaActiva() && ! in_array($u->id, $fuera, true))),
            array_filter($entidades, fn (Unidad $u) => $u->padre === null && $u->estaActiva() && $u->id !== $excluir) !== [],
        );
    }

    public function crear(DatosUnidad $datos, ?int $actorId): Unidad
    {
        $this->exigirExistencia($datos);
        if ($datos->unidadPadreId === null && $this->hayRaizActiva()) {
            throw UnidadInvalida::raizActivaYaExiste();
        }
        if ($datos->unidadPadreId !== null && $this->filas[$datos->unidadPadreId]['estado'] !== EstadoUnidad::Activa) {
            throw UnidadInvalida::padreInactivo();
        }

        $id = $this->agregar(trim($datos->nombre), $datos->unidadPadreId, $datos->tipoUnidadId);
        $this->filas[$id]['creadoPor'] = $actorId;
        $this->registrar($id, null, $this->instantanea($id), $actorId);
        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->obtener($id);
    }

    public function actualizar(int $id, int $versionEsperada, DatosUnidad $datos, ?int $actorId): Unidad
    {
        $fila = $this->filas[$id] ?? throw new UnidadNoEncontrada;
        $this->exigirExistencia($datos);

        if (trim($datos->nombre) === $fila['nombre'] && $datos->tipoUnidadId === $fila['tipo'] && $datos->unidadPadreId === $fila['padre']) {
            return $this->obtener($id);
        }
        if ($fila['version'] !== $versionEsperada) {
            throw new UnidadModificada;
        }

        if ($datos->unidadPadreId !== null && $datos->unidadPadreId !== $fila['padre']) {
            if ($datos->unidadPadreId === $id) {
                throw UnidadInvalida::padreEsLaMisma();
            }
            if (in_array($datos->unidadPadreId, $this->construir()['arbol']->descendientesDe($id), true)) {
                throw UnidadInvalida::padreEsDependiente();
            }
            if ($this->filas[$datos->unidadPadreId]['estado'] !== EstadoUnidad::Activa) {
                throw UnidadInvalida::padreInactivo();
            }
        }
        if ($datos->unidadPadreId === null && $fila['estado'] === EstadoUnidad::Activa && $this->hayRaizActiva($id)) {
            throw UnidadInvalida::raizActivaYaExiste();
        }

        $anterior = $this->instantanea($id);
        $this->filas[$id] = [...$fila, 'nombre' => trim($datos->nombre), 'tipo' => $datos->tipoUnidadId, 'padre' => $datos->unidadPadreId,
            'version' => $fila['version'] + 1, 'modificadoPor' => $actorId];
        $this->registrar($id, $anterior, $this->instantanea($id), $actorId);
        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->obtener($id);
    }

    public function cambiarEstado(int $id, EstadoUnidad $estado, ?int $actorId): Unidad
    {
        $fila = $this->filas[$id] ?? throw new UnidadNoEncontrada;
        if ($fila['estado'] === $estado) {
            return $this->obtener($id);
        }

        $anterior = $this->instantanea($id);
        if ($estado === EstadoUnidad::Inactiva) {
            $dependientes = count(array_filter($this->filas, fn ($f) => $f['padre'] === $id && $f['estado'] === EstadoUnidad::Activa));
            $posiciones = $this->posicionesVigentes[$id] ?? 0;
            if ($dependientes > 0 || $posiciones > 0) {
                throw new UnidadConDependenciasEnUso($dependientes, $posiciones);
            }
            $this->filas[$id]['estado'] = $estado;
            $this->filas[$id]['fechaSupresion'] = '2026-10-14';
        } else {
            if ($fila['padre'] !== null && $this->filas[$fila['padre']]['estado'] !== EstadoUnidad::Activa) {
                throw new UnidadNoReactivable(UnidadNoReactivable::PADRE_INACTIVO);
            }
            if ($fila['padre'] === null && $this->hayRaizActiva($id)) {
                throw new UnidadNoReactivable(UnidadNoReactivable::RAIZ_ACTIVA_EXISTENTE);
            }
            $this->filas[$id]['estado'] = $estado;
            $this->filas[$id]['fechaSupresion'] = null;
        }
        $this->registrar($id, $anterior, $this->instantanea($id), $actorId);
        $this->ultimoActor = $actorId;
        $this->escrituras++;

        return $this->obtener($id);
    }

    private function hayRaizActiva(?int $excepto = null): bool
    {
        return array_filter(
            $this->filas,
            fn ($f, $id) => $f['padre'] === null && $f['estado'] === EstadoUnidad::Activa && $id !== $excepto,
            ARRAY_FILTER_USE_BOTH,
        ) !== [];
    }

    private function exigirExistencia(DatosUnidad $datos): void
    {
        if (! isset($this->tiposDeUnidad[$datos->tipoUnidadId])) {
            throw UnidadInvalida::tipoInexistente();
        }
        if ($datos->unidadPadreId !== null && ! isset($this->filas[$datos->unidadPadreId])) {
            throw UnidadInvalida::padreInexistente();
        }
    }

    private function instantanea(int $id): InstantaneaDeUnidad
    {
        $f = $this->filas[$id];

        return new InstantaneaDeUnidad($f['nombre'], $f['tipo'], $f['padre'], $f['estado'], $f['fechaSupresion']);
    }

    private function registrar(int $id, ?InstantaneaDeUnidad $anterior, InstantaneaDeUnidad $nueva, ?int $actor): void
    {
        foreach ($this->comparador->comparar($anterior, $nueva) as $cambio) {
            $this->historial[] = ['unidad' => $id, 'campo' => $cambio->campo, 'anterior' => $cambio->valorAnterior, 'nuevo' => $cambio->valorNuevo, 'actor' => $actor];
        }
    }

    /**
     * @return array{entidades: array<int, Unidad>, arbol: ArbolDeUnidades}
     */
    private function construir(): array
    {
        $nodos = [];
        foreach ($this->filas as $id => $f) {
            $dependientes = count(array_filter($this->filas, fn ($h) => $h['padre'] === $id && $h['estado'] === EstadoUnidad::Activa));
            $nodos[$id] = new NodoDeUnidad($id, sprintf('UO-%03d', $id), $f['nombre'], $this->tiposDeUnidad[$f['tipo']], $f['padre'], $f['estado'], $dependientes, $this->posicionesVigentes[$id] ?? 0);
        }
        $arbol = new ArbolDeUnidades($nodos);

        $entidades = [];
        foreach ($arbol->ordenados() as $id) {
            $n = $nodos[$id];
            $f = $this->filas[$id];
            $padre = $n->unidadPadreId === null ? null : $nodos[$n->unidadPadreId];
            $entidades[$id] = new Unidad(
                $id, $n->codigo, $n->nombre, $n->tipo,
                $padre === null ? null : new UnidadResumida($padre->id, $padre->codigo, $padre->nombre),
                $arbol->nivelDe($id), $n->estado, $n->dependientesActivas, $n->posicionesVigentes, $f['version'],
                '2026-10-14', $f['fechaSupresion'],
                array_map(fn (int $h) => new UnidadResumida($h, $nodos[$h]->codigo, $nodos[$h]->nombre, $nodos[$h]->estado), $arbol->hijasDe($id)),
                $f['creadoPor'], $f['creadoPor'] === null ? null : '2026-10-14T10:00:00Z',
                $f['modificadoPor'], $f['modificadoPor'] === null ? null : '2026-10-14T11:00:00Z',
            );
        }

        return ['entidades' => $entidades, 'arbol' => $arbol];
    }
}
