<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\Exceptions\AprobadorInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaInvalidaEnPlan;
use App\Modules\Desarrollo\Domain\Exceptions\CupoExcedido;
use App\Modules\Desarrollo\Domain\Exceptions\FechasFueraDelAnio;
use App\Modules\Desarrollo\Domain\Exceptions\ParticipantesRepetidos;
use App\Modules\Desarrollo\Domain\Services\NormalizadorDeNombre;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use DateTimeImmutable;

/**
 * Valida los datos de un plan en Borrador contra las reglas de negocio que dependen de otros módulos o de varios
 * campos (competencias activas, fechas dentro del año, cupo, participantes con vínculo vigente, aprobador) y arma
 * la entidad. El formato de cada campo ya lo validó la Request.
 *
 * @phpstan-type DatosAccion array{id?: int|null, nombre: string, curso_id?: int|null, tipo?: string|null, fecha_inicio?: string|null, fecha_fin?: string|null, duracion_horas?: float|int|string|null, formador?: string|null, capacidad_maxima?: int|null, costo?: float|int|string|null, competencia_ids: list<int>, colaborador_ids?: list<int>}
 */
class ConstructorDePlan
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly ColaboradoresVigentesInterface $vigentes,
        private readonly AprobadoresInterface $aprobadores,
        private readonly RelojInterface $reloj,
        private readonly NormalizadorDeNombre $normalizador,
    ) {}

    /**
     * @param  array{nombre: string, anio: int, descripcion?: string|null, aprobador_id?: int|null, acciones?: list<array<string, mixed>>}  $datos
     */
    public function construir(int $tenantId, int $actorId, array $datos, ?Plan $actual = null): Plan
    {
        $anio = (int) $datos['anio'];
        $aprobadorId = isset($datos['aprobador_id']) ? (int) $datos['aprobador_id'] : null;
        if ($aprobadorId !== null && ! $this->aprobadores->esAdministradorActivo($tenantId, $aprobadorId)) {
            throw new AprobadorInvalido;
        }

        $hoy = $this->reloj->hoy($actorId)->format('Y-m-d');
        $acciones = [];
        foreach (array_values($datos['acciones'] ?? []) as $indice => $a) {
            $acciones[] = $this->accion($tenantId, $hoy, $anio, $indice, $a, $actual);
        }

        $nombre = $this->normalizador->limpiar($datos['nombre']);

        return new Plan(
            $actual?->id,
            $tenantId,
            $actual?->numero,
            $nombre,
            $this->normalizador->normalizar($nombre),
            $anio,
            isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== '' ? trim((string) $datos['descripcion']) : null,
            $actual?->estado ?? EstadoPlan::Borrador,
            $aprobadorId,
            $actual?->origenDnfId,
            $actual?->fechaAprobacion,
            $actual?->creadoPor ?? $actorId,
            $actorId,
            $actual?->creadoEn,
            null,
            $acciones,
        );
    }

    /**
     * Valida y arma una acción. Se usa también para las acciones creadas por la DNF.
     *
     * @param  array<string, mixed>  $a
     */
    public function accion(int $tenantId, string $hoy, int $anio, int $indice, array $a, ?Plan $actual = null): AccionFormativa
    {
        $prefijo = "acciones.{$indice}.";
        $existente = isset($a['id']) && $actual !== null ? $actual->accion((int) $a['id']) : null;

        $competenciaIds = array_values(array_unique(array_map('intval', $a['competencia_ids'] ?? [])));
        $nuevas = array_diff($competenciaIds, $existente?->competenciaIds ?? []);
        foreach ($nuevas as $competenciaId) {
            if (! $this->competencias->estaActiva($competenciaId)) {
                throw new CompetenciaInvalidaEnPlan($prefijo.'competencia_ids');
            }
        }

        $inicio = $this->fecha($a['fecha_inicio'] ?? null);
        $fin = $this->fecha($a['fecha_fin'] ?? null);
        foreach (['fecha_inicio' => $inicio, 'fecha_fin' => $fin] as $campo => $fecha) {
            if ($fecha !== null && (int) $fecha->format('Y') !== $anio) {
                throw new FechasFueraDelAnio($prefijo.$campo);
            }
        }

        $colaboradorIds = array_map('intval', $a['colaborador_ids'] ?? []);
        if (count($colaboradorIds) !== count(array_unique($colaboradorIds))) {
            throw new ParticipantesRepetidos($prefijo.'colaborador_ids');
        }
        $capacidad = isset($a['capacidad_maxima']) ? (int) $a['capacidad_maxima'] : null;
        if ($capacidad !== null && count($colaboradorIds) > $capacidad) {
            throw new CupoExcedido($prefijo.'colaborador_ids');
        }
        $agregados = array_values(array_diff($colaboradorIds, $existente?->colaboradorIds ?? []));
        if ($agregados !== [] && count($this->vigentes->vigentesPorIds($tenantId, $agregados, $hoy)) !== count($agregados)) {
            throw new ColaboradorInvalido($prefijo.'colaborador_ids');
        }

        return new AccionFormativa(
            $existente?->id,
            trim((string) $a['nombre']),
            isset($a['curso_id']) ? (int) $a['curso_id'] : null,
            isset($a['tipo']) && $a['tipo'] !== '' ? TipoAccion::from((string) $a['tipo']) : null,
            $inicio,
            $fin,
            isset($a['duracion_horas']) ? (float) $a['duracion_horas'] : null,
            isset($a['formador']) && trim((string) $a['formador']) !== '' ? trim((string) $a['formador']) : null,
            $capacidad,
            isset($a['costo']) ? round((float) $a['costo'], 2) : null,
            $existente?->cancelada ?? false,
            $existente?->motivoCancelacion,
            $competenciaIds,
            $colaboradorIds,
        );
    }

    private function fecha(mixed $valor): ?DateTimeImmutable
    {
        return $valor === null || $valor === '' ? null : new DateTimeImmutable((string) $valor);
    }
}
