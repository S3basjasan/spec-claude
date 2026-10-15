<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\ConfiguracionFormativa;
use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\ConfiguracionModificada;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDeAreaDuplicado;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;
use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;

interface CatalogoFormativoRepositoryInterface
{
    /**
     * Configuración de una competencia con todos sus recursos vinculados (incluidos los archivados).
     * Una competencia nunca configurada devuelve una configuración vacía de versión 0.
     */
    public function configuracionDe(int $competenciaId): ConfiguracionFormativa;

    /**
     * Configuraciones de varias competencias en pocas consultas; incluye una entrada vacía para cada id pedido.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, ConfiguracionFormativa> competencia_id => configuración
     */
    public function configuracionesDe(array $competenciaIds): array;

    /**
     * @return array<int, array{id: int, nombre: string}> Ordenadas por nombre.
     */
    public function areas(): array;

    /**
     * Recursos disponibles (no archivados) con la cantidad de competencias que los usan.
     *
     * @return array<int, RecursoFormativo>
     */
    public function recursosDisponibles(TipoRecurso $tipo, ?string $q, ?int $excluirCompetenciaId): array;

    /**
     * Ids de los recursos que existen y están disponibles.
     *
     * @return array<int, int>
     */
    public function idsDisponibles(TipoRecurso $tipo): array;

    /**
     * Reemplaza en una transacción el área, los cursos y los micro-contenidos de la competencia, y escribe el
     * historial. Sin diferencias reales no modifica nada.
     *
     * @throws ConfiguracionModificada
     */
    public function guardarConfiguracion(int $competenciaId, int $versionEsperada, DatosConfiguracion $datos, ?int $actorId): ConfiguracionFormativa;

    /**
     * @return array{id: int, nombre: string}
     *
     * @throws NombreDeAreaDuplicado
     */
    public function crearArea(string $nombre, ?int $actorId): array;

    /**
     * Renombra el área; si el nombre cambia escribe el historial. Sin cambio real no escribe nada.
     *
     * @return array{id: int, nombre: string}
     *
     * @throws AreaNoEncontrada
     * @throws NombreDeAreaDuplicado
     */
    public function renombrarArea(int $areaId, string $nombre, ?int $actorId): array;

    /**
     * Historial de la competencia más los renombrados de las áreas que ha tenido asignadas, del más reciente al más antiguo.
     *
     * @return array{items: array<int, array{fecha: string, usuario_id: ?int, tipo_cambio: string, accion: string, recurso: ?string, valor_anterior: ?string, valor_nuevo: ?string}>, total: int}
     */
    public function historialDe(int $competenciaId, int $pagina, int $porPagina): array;
}
