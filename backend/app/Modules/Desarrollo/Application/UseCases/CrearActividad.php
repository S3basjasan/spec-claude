<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Exceptions\AccionNoElegible;
use App\Modules\Desarrollo\Domain\Exceptions\AccionNoEncontradaParaActividad;
use App\Modules\Desarrollo\Domain\Exceptions\DepartamentoInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\LugarInvalido;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;

/** Crea la actividad de una acción no cancelada de un plan Ejecutando que aún no tiene actividad. */
final class CrearActividad
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly DepartamentosInterface $departamentos,
        private readonly ReglasDeActividad $reglas,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws AccionNoEncontradaParaActividad
     * @throws AccionNoElegible
     * @throws LugarInvalido
     * @throws DepartamentoInvalido
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $accionId, string $lugarEnlace, ?int $departamentoId): array
    {
        $accion = $this->acciones->obtener($tenantId, $accionId) ?? throw new AccionNoEncontradaParaActividad;
        if ($accion->cancelada || $accion->planEstado !== EstadoPlan::Ejecutando || $this->actividades->obtenerPorAccion($tenantId, $accionId) !== null) {
            throw new AccionNoElegible;
        }

        $this->reglas->validarLugar($accion->tipo, $lugarEnlace);
        if ($departamentoId !== null && ! $this->departamentos->estaActivo($departamentoId)) {
            throw new DepartamentoInvalido;
        }

        $actividad = $this->actividades->crear(new Actividad(null, $tenantId, null, $accionId, trim($lugarEnlace), $departamentoId, 1, $usuarioId, $usuarioId));

        return $this->armador->detalle($tenantId, $usuarioId, $actividad, $accion);
    }
}
