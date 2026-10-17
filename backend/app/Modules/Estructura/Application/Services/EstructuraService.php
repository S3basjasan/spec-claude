<?php

namespace App\Modules\Estructura\Application\Services;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Entities\Posicion;
use App\Modules\Estructura\Domain\Entities\Puesto;
use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;
use App\Modules\Estructura\Domain\Entities\RegistroPreguntas;
use App\Modules\Estructura\Domain\Entities\TipoUnidad;
use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\LecturaPosicionRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\TipoUnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\NodoDePosicion;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;

/**
 * Servicio inter-módulos del Módulo Estructura.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Es la única puerta de los demás módulos hacia el diccionario de competencias
 * (ver specs/001-diccionario-competencias/contracts/estructura-facade.md).
 */
class EstructuraService
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly ComportamientoRepositoryInterface $comportamientos,
        private readonly PreguntaRepositoryInterface $preguntas,
        private readonly PuestoRepositoryInterface $puestos,
        private readonly UnidadRepositoryInterface $unidades,
        private readonly TipoUnidadRepositoryInterface $tipos,
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly LecturaPosicionRepositoryInterface $lecturaPosiciones,
    ) {}

    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => 'Estructura'];
    }

    /**
     * Competencia con sus cuatro grados, activa o inactiva.
     *
     * @return array<string, mixed>|null
     */
    public function getCompetencia(int $id): ?array
    {
        $competencia = $this->competencias->obtener($id);

        return $competencia === null ? null : $this->aArreglo($competencia);
    }

    /**
     * Competencias existentes entre los ids dados, incluidas las inactivas (registros históricos).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function getCompetenciasPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map($this->aArreglo(...), $this->competencias->obtenerPorIds(array_values(array_unique($ids))));
    }

    public function existeCompetencia(int $id): bool
    {
        return $this->competencias->obtener($id) !== null;
    }

    /** Solo las competencias activas pueden asignarse en nuevos registros. */
    public function estaActiva(int $id): bool
    {
        return $this->competencias->obtener($id)?->estaActiva() ?? false;
    }

    /**
     * Competencias activas ordenadas por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCompetenciasActivas(): array
    {
        return array_map($this->aArreglo(...), $this->competencias->obtenerActivas());
    }

    /**
     * Todas las competencias, activas e inactivas, sin grados y ordenadas por nombre (para vistas de gestión).
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, descripcion: string, tipo: string, estado: string}>
     */
    public function getTodasLasCompetencias(): array
    {
        return array_map(fn (Competencia $c) => [
            'id' => $c->id,
            'codigo' => $c->codigo,
            'nombre' => $c->nombre,
            'descripcion' => $c->descripcion,
            'tipo' => $c->tipo->value,
            'estado' => $c->estado->value,
        ], $this->competencias->obtenerTodas());
    }

    /**
     * Registro de comportamientos de una competencia (activa o inactiva, para registros históricos), o null si no tiene.
     *
     * @return array<string, mixed>|null
     */
    public function getComportamientos(int $competenciaId): ?array
    {
        $registro = $this->comportamientos->obtener($competenciaId);

        return $registro === null ? null : $this->registroAArreglo($registro);
    }

    /**
     * Registros de las competencias indicadas que tienen comportamientos, incluidas las inactivas.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, mixed>>
     */
    public function getComportamientosPorCompetencias(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        return array_map($this->registroAArreglo(...), $this->comportamientos->obtenerPorCompetencias(array_values(array_unique($competenciaIds))));
    }

    /** Solo los comportamientos de competencias activas pueden asignarse en nuevos registros. */
    public function tieneComportamientosActivos(int $competenciaId): bool
    {
        return $this->comportamientos->obtener($competenciaId)?->competenciaActiva() ?? false;
    }

    /**
     * Registros de competencias activas ordenados por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getComportamientosActivos(): array
    {
        return array_map($this->registroAArreglo(...), $this->comportamientos->obtenerActivos());
    }

    /**
     * Registro de preguntas de una competencia (activa o inactiva, para registros históricos), o null si no tiene.
     *
     * @return array<string, mixed>|null
     */
    public function getPreguntas(int $competenciaId): ?array
    {
        $registro = $this->preguntas->obtener($competenciaId);

        return $registro === null ? null : $this->preguntasAArreglo($registro);
    }

    /**
     * Registros de preguntas de las competencias indicadas que tienen registro, incluidas las inactivas.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, mixed>>
     */
    public function getPreguntasPorCompetencias(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        return array_map($this->preguntasAArreglo(...), $this->preguntas->obtenerPorCompetencias(array_values(array_unique($competenciaIds))));
    }

    /** Solo las preguntas de competencias activas pueden asignarse en nuevos registros. */
    public function tienePreguntasActivas(int $competenciaId): bool
    {
        return $this->preguntas->obtener($competenciaId)?->competenciaActiva() ?? false;
    }

    /**
     * Registros de preguntas de competencias activas ordenados por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPreguntasActivas(): array
    {
        return array_map($this->preguntasAArreglo(...), $this->preguntas->obtenerActivos());
    }

    /**
     * Puesto con sus funciones, requisitos y competencias (activo o inactivo, para registros históricos), o null.
     *
     * @return array<string, mixed>|null
     */
    public function getPuesto(int $id): ?array
    {
        $puesto = $this->puestos->obtener($id);

        return $puesto === null ? null : $this->puestoAArreglo($puesto);
    }

    /**
     * Puestos existentes entre los ids dados, incluidos los inactivos.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function getPuestosPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map($this->puestoAArreglo(...), $this->puestos->obtenerPorIds(array_values(array_unique($ids))));
    }

    public function existePuesto(int $id): bool
    {
        return $this->puestos->obtener($id) !== null;
    }

    /** Solo los puestos activos pueden usarse para crear nuevas posiciones. */
    public function puestoEstaActivo(int $id): bool
    {
        return $this->puestos->obtener($id)?->activo() ?? false;
    }

    /**
     * Puestos activos ordenados por nombre, para el selector de nuevas posiciones.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, descripcion: string}>
     */
    public function getPuestosActivos(): array
    {
        return array_map(fn (Puesto $puesto) => [
            'id' => $puesto->id,
            'codigo' => $puesto->codigo,
            'nombre' => $puesto->nombre,
            'descripcion' => $puesto->descripcion,
        ], $this->puestos->obtenerActivos());
    }

    /**
     * Unidad (activa o inactiva, para registros históricos), o null si no existe. Sin datos de auditoría.
     *
     * @return array<string, mixed>|null
     */
    public function getUnidad(int $id): ?array
    {
        $unidad = $this->unidades->obtener($id);

        return $unidad === null ? null : $this->unidadAArreglo($unidad);
    }

    /**
     * Unidades existentes entre los ids dados, incluidas las inactivas.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function getUnidadesPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map($this->unidadAArreglo(...), $this->unidades->obtenerPorIds(array_values(array_unique($ids))));
    }

    public function existeUnidad(int $id): bool
    {
        return $this->unidades->obtener($id) !== null;
    }

    /** Solo las unidades activas pueden recibir nuevas posiciones y nuevas unidades dependientes. */
    public function unidadEstaActiva(int $id): bool
    {
        return $this->unidades->obtener($id)?->estaActiva() ?? false;
    }

    /**
     * Unidades activas en orden jerárquico, para el selector de unidad al crear posiciones.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo: array{id: int, nombre: string}, nivel_arbol: int}>
     */
    public function getUnidadesActivas(): array
    {
        return array_values(array_map(fn (Unidad $unidad) => [
            'id' => $unidad->id,
            'codigo' => $unidad->codigo,
            'nombre' => $unidad->nombre,
            'tipo' => ['id' => $unidad->tipo->id, 'nombre' => $unidad->tipo->nombre],
            'nivel_arbol' => $unidad->nivel,
        ], array_filter($this->unidades->todas(), fn (Unidad $unidad) => $unidad->estaActiva())));
    }

    /**
     * Árbol completo (activas e inactivas) en orden jerárquico, para el organigrama.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getArbolDeUnidades(): array
    {
        return array_map($this->unidadAArreglo(...), $this->unidades->todas());
    }

    /**
     * @return array<string, mixed>
     */
    private function unidadAArreglo(Unidad $unidad): array
    {
        return [
            'id' => $unidad->id,
            'codigo' => $unidad->codigo,
            'nombre' => $unidad->nombre,
            'tipo' => ['id' => $unidad->tipo->id, 'nombre' => $unidad->tipo->nombre, 'nivel' => $unidad->tipo->nivel],
            'unidad_padre' => $unidad->padre === null ? null : [
                'id' => $unidad->padre->id,
                'codigo' => $unidad->padre->codigo,
                'nombre' => $unidad->padre->nombre,
            ],
            'nivel_arbol' => $unidad->nivel,
            'estado' => $unidad->estado->value,
            'fecha_creacion' => $unidad->fechaCreacion,
            'fecha_supresion' => $unidad->fechaSupresion,
            'dependientes_activas' => $unidad->dependientesActivas,
            'posiciones_vigentes' => $unidad->posicionesVigentes,
            'admite_hijas' => $unidad->admiteHijas,
            'sigue_cadena' => $unidad->sigueCadena,
        ];
    }

    /**
     * La cadena de tipos de unidad ordenada por nivel (el nivel 1 es el tipo raíz).
     *
     * @return array<int, array{id: int, nombre: string, nivel: int, es_raiz: bool}>
     */
    public function getCadenaDeTipos(): array
    {
        return array_map(fn (TipoUnidad $tipo) => [
            'id' => $tipo->id,
            'nombre' => $tipo->nombre,
            'nivel' => $tipo->nivel,
            'es_raiz' => $tipo->esRaiz(),
        ], $this->tipos->todos());
    }

    /**
     * @return array{id: int, nombre: string, nivel: int, es_raiz: bool}|null
     */
    public function getTipoDeUnidad(int $id): ?array
    {
        $tipo = $this->tipos->obtener($id);

        return $tipo === null ? null : [
            'id' => $tipo->id,
            'nombre' => $tipo->nombre,
            'nivel' => $tipo->nivel,
            'es_raiz' => $tipo->esRaiz(),
        ];
    }

    /**
     * Posición (también Suprimida, para registros históricos), o null si no existe. Sin datos de auditoría.
     *
     * @return array<string, mixed>|null
     */
    public function getPosicion(int $id): ?array
    {
        $posicion = $this->posiciones->obtener($id);

        return $posicion === null ? null : $this->posicionAArreglo($posicion);
    }

    /**
     * Posiciones existentes entre los ids dados, incluidas las Suprimidas.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function getPosicionesPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map($this->posicionAArreglo(...), $this->posiciones->obtenerPorIds(array_values(array_unique($ids))));
    }

    public function existePosicion(int $id): bool
    {
        return $this->posiciones->obtener($id) !== null;
    }

    /** Vigente = Ocupada o Vacante: la única que puede ocuparse, supervisar o recibir subordinadas. */
    public function posicionEstaVigente(int $id): bool
    {
        return $this->posiciones->obtener($id)?->esVigente() ?? false;
    }

    /**
     * Posiciones no Suprimidas con su supervisor, en orden de código, para el organigrama y el perfilamiento.
     *
     * @return array<int, array{id: int, codigo: string, estado: string, supervisor_id: int|null}>
     */
    public function getArbolDeSupervision(): array
    {
        return array_map(fn (NodoDePosicion $nodo) => [
            'id' => $nodo->id,
            'codigo' => $nodo->codigo,
            'estado' => $nodo->estado->value,
            'supervisor_id' => $nodo->supervisorId,
        ], $this->posiciones->arbolDeSupervision());
    }

    /**
     * @return array<string, mixed>
     */
    private function posicionAArreglo(Posicion $posicion): array
    {
        return [
            'id' => $posicion->id,
            'codigo' => $posicion->codigo,
            'estado' => $posicion->estado->value,
            'modalidad' => $posicion->modalidad->value,
            'puesto' => [
                'id' => $posicion->puesto['id'],
                'codigo' => $posicion->puesto['codigo'],
                'nombre' => $posicion->puesto['nombre'],
            ],
            'unidad' => [
                'id' => $posicion->unidad['id'],
                'codigo' => $posicion->unidad['codigo'],
                'nombre' => $posicion->unidad['nombre'],
                'estado' => $posicion->unidad['estado'],
            ],
            'supervisor' => $posicion->supervisor === null ? null : [
                'id' => $posicion->supervisor['id'],
                'codigo' => $posicion->supervisor['codigo'],
                'nombre' => $posicion->supervisor['puesto'],
            ],
            'ocupante_id' => $posicion->ocupanteId,
            'subordinadas_vigentes' => $posicion->subordinadasVigentes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    // ------------------------------------------------------------------ Posiciones (GP-02)

    /**
     * Posición con su puesto y unidad vigentes, o null. La jefatura es null hasta que Estructura la publique.
     *
     * @return array<string, mixed>|null
     */
    public function getPosicionLaboral(int $id): ?array
    {
        return $this->lecturaPosiciones->obtener($id);
    }

    /**
     * Posiciones existentes entre los ids dados, incluidas las suprimidas (histórico).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>> Indexado por id.
     */
    public function getPosicionesLaboralesPorIds(array $ids): array
    {
        return $this->lecturaPosiciones->porIds($ids);
    }

    /**
     * Posiciones del tenant que admiten nuevas asignaciones (no suprimidas, puesto activo y unidad activa);
     * no considera la ocupación.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function posicionesAsignables(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
    {
        return $this->lecturaPosiciones->asignables($tenantId, $texto, $pagina, $porPagina);
    }

    /**
     * Si una posición admite nuevas asignaciones para el tenant. Se evalúa en la conexión y la transacción de quien
     * llama (no abre otra).
     *
     * @return array{asignable: bool, motivo: string|null}
     */
    public function posicionEsAsignable(int $id, int $tenantId): array
    {
        $motivo = $this->lecturaPosiciones->motivoNoAsignable($id, $tenantId);

        return ['asignable' => $motivo === null, 'motivo' => $motivo];
    }

    /** @return array<int, int> */
    public function getPosicionesPorPuesto(string $texto): array
    {
        return $this->lecturaPosiciones->idsPorPuesto($texto);
    }

    /** @return array<int, int> */
    public function getPosicionesPorUnidad(int $unidadId): array
    {
        return $this->lecturaPosiciones->idsPorUnidad($unidadId);
    }

    private function puestoAArreglo(Puesto $puesto): array
    {
        return [
            'id' => $puesto->id,
            'codigo' => $puesto->codigo,
            'nombre' => $puesto->nombre,
            'descripcion' => $puesto->descripcion,
            'mision' => $puesto->mision,
            'estado' => $puesto->estado->value,
            'funciones' => array_map(fn (FuncionPuesto $f) => [
                'orden' => $f->orden,
                'descripcion' => $f->descripcion,
                'tipo' => $f->tipo->value,
            ], $puesto->funciones),
            'requisitos' => array_map(fn (RequisitoPuesto $r) => [
                'tipo' => $r->tipo->value,
                'descripcion' => $r->descripcion,
            ], $puesto->requisitos),
            'competencias' => array_map(fn (CompetenciaDePuesto $c) => [
                'competencia_id' => $c->competencia->id,
                'codigo' => $c->competencia->codigo,
                'nombre' => $c->competencia->nombre,
                'tipo' => $c->competencia->tipo->value,
                'estado' => $c->competencia->estado->value,
                'grado_requerido' => $c->gradoRequerido,
            ], $puesto->competencias),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preguntasAArreglo(RegistroPreguntas $registro): array
    {
        $competencia = $registro->competencia;

        return [
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'preguntas' => $registro->preguntas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registroAArreglo(RegistroComportamientos $registro): array
    {
        $competencia = $registro->competencia;

        return [
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'comportamientos' => $registro->comportamientos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aArreglo(Competencia $competencia): array
    {
        return [
            'id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'grados' => $competencia->grados,
        ];
    }
}
