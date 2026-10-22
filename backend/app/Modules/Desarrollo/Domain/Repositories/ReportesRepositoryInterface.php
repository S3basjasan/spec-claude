<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\EncuestaSatisfaccion;
use App\Modules\Desarrollo\Domain\Entities\ImpactoAccion;
use App\Modules\Desarrollo\Domain\Entities\SeguimientoImpacto;
use App\Modules\Desarrollo\Domain\Exceptions\EncuestaNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\SeguimientoNoPermitido;

/**
 * Persistencia de lo único que captura Reportes: encuestas de satisfacción, seguimientos 30-60-90 y evaluaciones de impacto.
 * Encuestas y seguimientos son inmutables: no existe forma de actualizarlos ni de eliminarlos.
 */
interface ReportesRepositoryInterface
{
    /** @throws EncuestaNoPermitida con `ya_respondida` si ya existe una del colaborador */
    public function registrarEncuesta(EncuestaSatisfaccion $encuesta): EncuestaSatisfaccion;

    /**
     * @param  list<int>|null  $actividadIds  nulo = todas las del tenant
     * @return list<EncuestaSatisfaccion>
     */
    public function encuestasDe(int $tenantId, ?array $actividadIds = null): array;

    /** @return list<EncuestaSatisfaccion> */
    public function encuestasDeColaborador(int $tenantId, int $colaboradorId): array;

    public function encuestaDe(int $actividadId, int $colaboradorId): ?EncuestaSatisfaccion;

    /**
     * Comentarios de la actividad, del más reciente al más antiguo, sin ningún dato del autor y con la fecha solo en día.
     *
     * @return array{items: list<array{calificacion: int, comentario: string, fecha: string}>, total: int}
     */
    public function comentarios(int $tenantId, int $actividadId, int $pagina, int $porPagina): array;

    /** @throws SeguimientoNoPermitido con `ya_respondida` si ya existe ese hito */
    public function registrarSeguimiento(SeguimientoImpacto $seguimiento): SeguimientoImpacto;

    /**
     * @param  list<int>|null  $actividadIds  nulo = todas las del tenant
     * @return list<SeguimientoImpacto>
     */
    public function seguimientosDe(int $tenantId, ?array $actividadIds = null): array;

    /** @return list<SeguimientoImpacto> */
    public function seguimientosDeColaborador(int $tenantId, int $colaboradorId): array;

    public function seguimientoDe(int $actividadId, int $colaboradorId, int $hito): ?SeguimientoImpacto;

    public function impactoDe(int $tenantId, int $planAccionId): ?ImpactoAccion;

    /**
     * @param  list<int>|null  $planAccionIds  nulo = todas las del tenant
     * @return array<int, ImpactoAccion> plan_accion_id => evaluación
     */
    public function impactosDe(int $tenantId, ?array $planAccionIds = null): array;

    /** Crea la evaluación de la acción o reemplaza la existente en una transacción; conserva el creador original. */
    public function guardarImpacto(ImpactoAccion $impacto, int $usuarioId): ImpactoAccion;
}
