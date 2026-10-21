<?php

namespace App\Modules\Estructura\Application\Services;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Entities\Puesto;
use App\Modules\Estructura\Domain\Entities\TipoUnidad;
use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
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
        private readonly PuestoRepositoryInterface $puestos,
        private readonly UnidadRepositoryInterface $unidades,
        private readonly TipoUnidadRepositoryInterface $tipos,
        private readonly PosicionRepositoryInterface $posiciones,
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
     * Comportamientos de una competencia (activa o inactiva, para registros históricos), o null si no tiene ninguno.
     *
     * @return array<string, mixed>|null
     */
    public function getComportamientos(int $competenciaId): ?array
    {
        $competencia = $this->competencias->obtener($competenciaId);

        return $competencia === null || ! $this->tieneComportamientos($competencia) ? null : $this->comportamientosAArreglo($competencia);
    }

    /**
     * Comportamientos de las competencias indicadas que tienen alguno, incluidas las inactivas.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, mixed>>
     */
    public function getComportamientosPorCompetencias(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        $resultado = [];
        foreach ($this->competencias->obtenerPorIds(array_values(array_unique($competenciaIds))) as $competencia) {
            if ($this->tieneComportamientos($competencia)) {
                $resultado[] = $this->comportamientosAArreglo($competencia);
            }
        }

        return $resultado;
    }

    /** Solo los comportamientos de competencias activas y completas pueden asignarse en nuevos registros. */
    public function tieneComportamientosActivos(int $competenciaId): bool
    {
        $competencia = $this->competencias->obtener($competenciaId);

        return $competencia !== null && $competencia->estaActiva() && $competencia->estaCompleta();
    }

    /**
     * Comportamientos de competencias activas y completas ordenados por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getComportamientosActivos(): array
    {
        return array_map(
            $this->comportamientosAArreglo(...),
            array_values(array_filter($this->competencias->obtenerActivas(), fn (Competencia $c) => $c->estaCompleta())),
        );
    }

    /**
     * Preguntas de una competencia (activa o inactiva, para registros históricos), o null si no tiene ninguna.
     *
     * @return array<string, mixed>|null
     */
    public function getPreguntas(int $competenciaId): ?array
    {
        $competencia = $this->competencias->obtener($competenciaId);

        return $competencia === null || $competencia->preguntas === [] ? null : $this->preguntasAArreglo($competencia);
    }

    /**
     * Preguntas de las competencias indicadas que tienen alguna, incluidas las inactivas.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, mixed>>
     */
    public function getPreguntasPorCompetencias(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        $resultado = [];
        foreach ($this->competencias->obtenerPorIds(array_values(array_unique($competenciaIds))) as $competencia) {
            if ($competencia->preguntas !== []) {
                $resultado[] = $this->preguntasAArreglo($competencia);
            }
        }

        return $resultado;
    }

    /** Solo las preguntas de competencias activas y completas pueden asignarse en nuevos registros. */
    public function tienePreguntasActivas(int $competenciaId): bool
    {
        $competencia = $this->competencias->obtener($competenciaId);

        return $competencia !== null && $competencia->estaActiva() && $competencia->estaCompleta();
    }

    /**
     * Preguntas de competencias activas y completas ordenadas por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPreguntasActivas(): array
    {
        return array_map(
            $this->preguntasAArreglo(...),
            array_values(array_filter($this->competencias->obtenerActivas(), fn (Competencia $c) => $c->estaCompleta())),
        );
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

    // ------------------------------------------------------------------ Posiciones (GP-02)

    /**
     * Posición (también Suprimida) con su puesto, unidad y jefatura (puesto del supervisor, o null) vigentes, o null si no existe.
     *
     * @return array<string, mixed>|null
     */
    public function getPosicionDeGestion(int $id): ?array
    {
        return $this->posiciones->obtener($id);
    }

    /**
     * Posiciones existentes entre los ids dados, incluidas las suprimidas (histórico).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>> Indexado por id.
     */
    public function getPosicionesDeGestionPorIds(array $ids): array
    {
        return $this->posiciones->datosPorIds(array_values(array_unique($ids)));
    }

    /**
     * Posiciones del tenant que admiten nuevas asignaciones (no suprimidas, puesto activo y unidad activa);
     * no considera la ocupación.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function posicionesAsignables(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
    {
        return $this->posiciones->asignables($tenantId, $texto, $pagina, $porPagina);
    }

    /**
     * Si una posición admite nuevas asignaciones para el tenant. Se evalúa en la conexión y la transacción de quien
     * llama (no abre otra).
     *
     * @return array{asignable: bool, motivo: string|null}
     */
    public function posicionEsAsignable(int $id, int $tenantId): array
    {
        $motivo = $this->posiciones->motivoNoAsignable($id, $tenantId);

        return ['asignable' => $motivo === null, 'motivo' => $motivo];
    }

    /** @return array<int, int> */
    public function getPosicionesPorPuesto(string $texto): array
    {
        return $this->posiciones->idsPorPuesto($texto);
    }

    /** @return array<int, int> */
    public function getPosicionesPorUnidad(int $unidadId): array
    {
        return $this->posiciones->idsPorUnidad($unidadId);
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

    private function tieneComportamientos(Competencia $competencia): bool
    {
        foreach ($competencia->gradosDetalle as $grado) {
            if ($grado->comportamientos !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function preguntasAArreglo(Competencia $competencia): array
    {
        return [
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'completa' => $competencia->estaCompleta(),
            'preguntas' => $competencia->preguntas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function comportamientosAArreglo(Competencia $competencia): array
    {
        $porGrado = [];
        foreach ($competencia->gradosDetalle as $grado) {
            $porGrado[$grado->clave] = $grado->comportamientos;
        }

        return [
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'completa' => $competencia->estaCompleta(),
            'comportamientos' => $porGrado,
        ];
    }

    /**
     * `grados` conserva el mapa por clave posicional (A–D) que usan los demás módulos; `escala`,
     * `grados_detalle` y `completa` son las claves nuevas del diccionario unificado.
     *
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
            'escala' => $competencia->escala === null ? null : ['tipo_grado_id' => $competencia->escala->id, 'nombre' => $competencia->escala->nombre],
            'grados_detalle' => array_map(fn ($g) => [
                'id' => $g->id,
                'orden' => $g->orden,
                'clave' => $g->clave,
                'etiqueta' => $g->etiqueta,
                'descripcion' => $g->descripcion,
                'comportamientos' => $g->comportamientos,
            ], $competencia->gradosDetalle),
            'completa' => $competencia->estaCompleta(),
        ];
    }
}
