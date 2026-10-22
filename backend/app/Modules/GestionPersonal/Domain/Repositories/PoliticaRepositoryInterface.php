<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\AccionDePolitica;
use App\Modules\GestionPersonal\Domain\Entities\PoliticaDePersonal;
use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorExistente;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use DateTimeImmutable;

/**
 * Persistencia de políticas y versiones. Sin ningún método de eliminación de versiones ni de acciones: las versiones
 * publicadas no se editan ni se eliminan.
 */
interface PoliticaRepositoryInterface
{
    /** Inserta de forma idempotente las siete cabeceras del tenant que falten. */
    public function asegurarCabeceras(int $tenantId): void;

    /** @return array<string, PoliticaDePersonal> por valor del tipo; solo las cabeceras que existen. */
    public function cabeceras(int $tenantId): array;

    public function cabecera(int $tenantId, TipoDePolitica $tipo): ?PoliticaDePersonal;

    /**
     * Toma el bloqueo de la cabecera hasta el fin de la transacción del llamador (actualización sin cambio de valor).
     *
     * @throws PoliticaNoEncontrada
     */
    public function bloquear(int $tenantId, TipoDePolitica $tipo): PoliticaDePersonal;

    /**
     * Versiones no descartadas de las políticas dadas.
     *
     * @param  list<int>  $politicaIds
     * @return array<int, list<VersionDePolitica>> por id de política
     */
    public function versionesDe(int $tenantId, array $politicaIds): array;

    public function version(int $tenantId, int $politicaId, int $versionId): ?VersionDePolitica;

    /** Reserva el siguiente número de publicación (solo con la cabecera bloqueada). */
    public function siguienteNumero(int $tenantId, int $politicaId): int;

    /**
     * @param  array<string, mixed>  $parametros
     *
     * @throws BorradorExistente
     */
    public function crearBorrador(int $tenantId, int $politicaId, int $actorId, string $descripcion, array $parametros, ?int $baseVersionId, DateTimeImmutable $ahora): VersionDePolitica;

    /**
     * @param  array<string, mixed>  $parametros
     *
     * @throws BorradorModificado
     */
    public function actualizarBorrador(int $tenantId, int $versionId, int $versionEsperada, int $actorId, string $descripcion, array $parametros, DateTimeImmutable $ahora): VersionDePolitica;

    /** @throws BorradorModificado */
    public function descartarBorrador(int $tenantId, int $versionId, int $versionEsperada, int $actorId, DateTimeImmutable $ahora): void;

    /**
     * Sella un borrador al publicar; los parámetros se guardan en su forma canónica, igual que en `condiciones`.
     *
     * @param  array<string, mixed>  $parametros
     * @param  array<string, mixed>  $condiciones
     *
     * @throws BorradorModificado
     */
    public function publicar(
        int $tenantId,
        int $versionId,
        int $versionEsperada,
        int $numero,
        int $actorId,
        DateTimeImmutable $ahora,
        DateTimeImmutable $inicioVigencia,
        string $motivo,
        string $revisor,
        ?string $observaciones,
        array $parametros,
        array $condiciones,
        string $condicionesJson,
        string $hash,
    ): VersionDePolitica;

    /** @throws AppModulesGestionPersonalDomainxceptionsVersionNoRetirable */
    public function retirar(int $tenantId, int $versionId, int $actorId, DateTimeImmutable $ahora, string $justificacion): VersionDePolitica;

    /** @throws ClaveDeOperacionReutilizada */
    public function registrarAccion(int $tenantId, int $politicaId, int $versionId, string $accion, int $actorId, ?string $motivo, ?string $operacionId, ?string $operacionHash, DateTimeImmutable $ahora): AccionDePolitica;

    public function accionPorOperacion(int $tenantId, string $operacionId): ?AccionDePolitica;
}
