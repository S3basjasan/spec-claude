<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\EntrevistaCerrada;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\SalidaYaFormalizada;

/**
 * Persistencia de expedientes de offboarding, tareas, entrevistas, respuestas y eventos. Sin ningún método de
 * eliminación; toda lectura y escritura lleva el tenant. Las escrituras se llaman dentro de una transacción abierta.
 */
interface ExpedienteRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?Expediente;

    /** Salida no cancelada más reciente de la persona (la que ve el Colaborador). */
    public function vigenteDeColaborador(int $tenantId, int $colaboradorId): ?Expediente;

    /** @return array{expediente: Expediente, hash: string, actor: int}|null */
    public function porOperacion(int $tenantId, string $operacionId): ?array;

    /** @return array{expediente_id: int, hash: string, actor_id: int|null}|null */
    public function eventoPorOperacion(int $tenantId, string $operacionId): ?array;

    /**
     * Inserta el expediente con las tareas de la plantilla, la tarea de entrevista, la entrevista pendiente, los
     * respaldos y el evento `Formalizada`.
     *
     * @param  array<string, mixed>  $comun
     * @param  list<array{nombre: string, descripcion: string|null, exige_evidencia: bool}>  $tareasDePlantilla
     * @param  list<int>  $documentoIds
     *
     * @throws SalidaYaFormalizada
     * @throws ConflictoDeUnicidad
     */
    public function insertar(array $comun, array $tareasDePlantilla, array $documentoIds): Expediente;

    /**
     * Actualiza columnas del expediente con control optimista de versión y agrega el evento.
     *
     * @param  array<string, mixed>  $columnas
     * @param  array<string, mixed>  $detalle
     *
     * @throws ExpedienteModificado
     * @throws ConflictoDeUnicidad
     */
    public function actualizar(Expediente $actual, array $columnas, string $evento, ?int $actorId, array $detalle = [], ?string $operacionId = null, ?string $operacionHash = null): Expediente;

    /**
     * @param  list<int>  $documentoIds
     *
     * @throws ExpedienteModificado
     */
    public function completarTarea(Expediente $actual, int $tareaId, int $actorId, array $documentoIds, ?string $operacionId, ?string $operacionHash): Expediente;

    /**
     * Reemplaza lo respondido mientras la entrevista no esté cerrada y la deja «en curso».
     *
     * @param  list<array{pregunta_id: string, tipo: string, dimension_o_categoria: string, valor: int|null, texto: string|null}>  $filas
     *
     * @throws EntrevistaCerrada
     * @throws ExpedienteModificado
     */
    public function guardarRespuestas(Expediente $actual, int $versionDeEntrevista, array $filas, int $actorId, ?string $operacionId, ?string $operacionHash): Expediente;

    /**
     * Cierra la entrevista (completada o no realizada), completa su tarea y agrega el evento.
     *
     * @param  array<string, mixed>  $columnas  Columnas de `offboarding_entrevistas` (estado, fechas, motivo…).
     *
     * @throws EntrevistaCerrada
     * @throws ExpedienteModificado
     */
    public function resolverEntrevista(Expediente $actual, int $versionDeEntrevista, array $columnas, string $evento, ?int $actorId, ?string $operacionId, ?string $operacionHash): Expediente;

    /** @return list<array{pregunta_id: string, tipo: string, dimension_o_categoria: string, valor: int|null, texto: string|null}> */
    public function respuestasDe(int $tenantId, int $entrevistaId): array;

    /** @param array<string, mixed> $detalle */
    public function registrarEvento(int $tenantId, int $expedienteId, string $evento, ?int $actorId, array $detalle = []): void;

    /** @return list<array<string, mixed>> */
    public function eventos(int $tenantId, int $expedienteId): array;

    /**
     * @param  array{estado?: string|null, cierre?: string|null, motivo?: string|null, entrevista?: string|null, colaborador_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listar(int $tenantId, array $filtros, int $pagina, int $porPagina): array;

    /** @return array<string, int> */
    public function resumen(int $tenantId): array;

    /** @return array{completadas: int, respuestas: list<array{entrevista_id: int, pregunta_id: string, tipo: string, valor: int|null}>} */
    public function datosParaAgregados(int $tenantId): array;

    /** @return list<int> */
    public function tenantsConSalidasPorAplicar(): array;

    /** @return list<int> Expedientes `Programada` con fecha efectiva alcanzada. */
    public function salidasPorAplicar(int $tenantId, string $hastaFecha, int $limite): array;

    /** @return list<array{expediente_id: int, vinculo_id: int, fecha_efectiva: string, motivo: string, politica_numero: int, aplicada_en: string}> */
    public function hechosEfectivos(int $tenantId, int $colaboradorId): array;

    /** @return array{codigo: string, nombre: string}|null */
    public function fichaResumen(int $tenantId, int $colaboradorId): ?array;

    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function nombresDeCuentas(array $userIds): array;
}
