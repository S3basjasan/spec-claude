<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;

/**
 * Persistencia de las adecuaciones. Es deliberadamente de solo inserción y lectura: una adecuación guardada no se
 * actualiza ni se elimina. La vigente de un colaborador para un puesto objetivo es la más reciente.
 */
interface AdecuacionesRepositoryInterface
{
    /** Guarda cabecera y resultados en una transacción y asigna el código ADE-NNNN. */
    public function guardar(Adecuacion $adecuacion): Adecuacion;

    public function obtener(int $tenantId, int $id): ?Adecuacion;

    /** La adecuación es la más reciente de su colaborador para su puesto objetivo. */
    public function esVigente(int $tenantId, Adecuacion $adecuacion): bool;

    /**
     * Historial paginado, de la más reciente a la más antigua. Filtros: fecha_desde, fecha_hasta, colaborador_id,
     * puesto_objetivo_id, preparacion.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{items: list<array{id: int, codigo: string, creado_en: string, colaborador_id: int, puesto_objetivo_id: int, preparacion: string, brechas: int, es_vigente: bool}>, total: int}
     */
    public function listar(int $tenantId, array $filtros, int $pagina, int $porPagina): array;

    /**
     * Adecuaciones vigentes (una por colaborador y puesto objetivo) con sus resultados, sin N+1. Filtros: preparacion,
     * ruta_id, puesto_objetivo_id, colaborador_ids.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<Adecuacion>
     */
    public function vigentes(int $tenantId, array $filtros = []): array;

    /**
     * Vigentes de un colaborador, la más reciente primero.
     *
     * @return list<Adecuacion>
     */
    public function vigentesDeColaborador(int $tenantId, int $colaboradorId): array;

    /**
     * Las últimas adecuaciones de un colaborador (todas, no solo las vigentes), con resultados.
     *
     * @return list<Adecuacion>
     */
    public function ultimasDeColaborador(int $tenantId, int $colaboradorId, int $limite): array;

    /**
     * Ruta de cada inscripción de origen.
     *
     * @param  list<int>  $inscripcionIds
     * @return array<int, array{id: int, nombre: string}> inscripcion_id => ruta
     */
    public function rutasDeInscripciones(array $inscripcionIds): array;

    /** Parámetros vigentes; si el tenant no los tiene, se crean con 40/30/30 y 12. */
    public function obtenerParametros(int $tenantId): ParametrosDeAdecuacion;

    /**
     * Guarda los parámetros y una fila de historial por campo cambiado, en una transacción.
     *
     * @param  list<array{campo: string, anterior: string, nuevo: string}>  $cambios
     */
    public function guardarParametros(int $tenantId, ParametrosDeAdecuacion $parametros, array $cambios, int $actorId): void;

    /**
     * @return array{items: list<array{campo: string, valor_anterior: string, valor_nuevo: string, user_id: int, fecha: string}>, total: int}
     */
    public function historialDeParametros(int $tenantId, int $pagina, int $porPagina): array;
}
