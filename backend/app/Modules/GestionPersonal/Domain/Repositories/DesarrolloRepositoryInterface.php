<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\ExperienciaAnterior;
use App\Modules\GestionPersonal\Domain\Entities\FormacionAcademica;
use App\Modules\GestionPersonal\Domain\Entities\Movilidad;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroNoEncontrado;

/**
 * Persistencia de desarrollo profesional. Sin ningún método de eliminación: los registros se corrigen, no se borran.
 */
interface DesarrolloRepositoryInterface
{
    /** @return list<FormacionAcademica> Por inicio desc (sin fecha al final) e id desc. */
    public function formaciones(int $tenantId, int $colaboradorId): array;

    /** @return list<ExperienciaAnterior> Por inicio desc e id desc. */
    public function experiencias(int $tenantId, int $colaboradorId): array;

    public function movilidad(int $tenantId, int $colaboradorId): ?Movilidad;

    public function formacion(int $tenantId, int $colaboradorId, int $id): ?FormacionAcademica;

    public function experiencia(int $tenantId, int $colaboradorId, int $id): ?ExperienciaAnterior;

    /** @param array<string, mixed> $datos */
    public function crearFormacion(int $tenantId, int $colaboradorId, int $actorId, array $datos): FormacionAcademica;

    /** @param array<string, mixed> $datos */
    public function crearExperiencia(int $tenantId, int $colaboradorId, int $actorId, array $datos): ExperienciaAnterior;

    /**
     * @param  array<string, mixed>  $cambios
     *
     * @throws RegistroNoEncontrado
     * @throws RegistroModificado
     */
    public function actualizarFormacion(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId, array $cambios): FormacionAcademica;

    /**
     * @param  array<string, mixed>  $cambios
     *
     * @throws RegistroNoEncontrado
     * @throws RegistroModificado
     */
    public function actualizarExperiencia(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId, array $cambios): ExperienciaAnterior;

    /**
     * @param  array<string, mixed>  $cambios
     *
     * @throws RegistroNoEncontrado
     * @throws RegistroModificado
     */
    public function actualizarMovilidad(int $tenantId, int $colaboradorId, int $version, int $actorId, array $cambios): Movilidad;

    /**
     * Lectura por lote con una consulta por tabla.
     *
     * @param  list<int>  $colaboradorIds
     * @return array<int, array{formaciones: list<FormacionAcademica>, experiencias: list<ExperienciaAnterior>, movilidad: Movilidad}>
     */
    public function deColaboradores(int $tenantId, array $colaboradorIds): array;
}
