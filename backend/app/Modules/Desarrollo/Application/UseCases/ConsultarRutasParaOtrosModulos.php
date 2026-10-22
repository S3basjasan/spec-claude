<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;

/**
 * Consultas de solo lectura que ofrece la Facade a Adecuación, al Plan de Desarrollo Individual y a Reportes. No incluyen
 * competencias ni requisitos: el consumidor los lee de Estructura por `puesto_id`.
 */
final class ConsultarRutasParaOtrosModulos
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly ConciliarBajasDeInscripciones $conciliar,
    ) {}

    /**
     * Inscripciones Activas del colaborador. Si ya no tiene vínculo vigente, se retiran y no se devuelven.
     *
     * @return list<array<string, mixed>>
     */
    public function inscripcionesActivas(int $tenantId, int $colaboradorId): array
    {
        $this->conciliar->ejecutar($tenantId, null, null, $colaboradorId);

        /** @var array<int, Ruta|null> $cargadas */
        $cargadas = [];
        $resultado = [];
        foreach ($this->rutas->activasDeColaborador($tenantId, $colaboradorId) as $i) {
            $ruta = $cargadas[$i->rutaId] ??= $this->rutas->obtener($tenantId, $i->rutaId);
            if ($ruta === null) {
                continue;
            }
            $resultado[] = [
                'inscripcion_id' => $i->id,
                'ruta_id' => $ruta->id,
                'ruta_codigo' => $ruta->codigo(),
                'ruta_nombre' => $ruta->nombre,
                'ruta_tipo' => $ruta->tipo->value,
                'nivel_actual' => $this->nivel($i->nivelActualId, $ruta, false),
                'nivel_objetivo' => $this->nivel($i->nivelObjetivoId, $ruta, true),
            ];
        }

        return $resultado;
    }

    /** @return array{nivel_id: int, ruta_id: int, ruta_nombre: string, orden: int, puesto_id: int, nombre: string, experiencia_minima_anios: float|null}|null */
    public function obtenerNivel(int $tenantId, int $nivelId): ?array
    {
        $nivel = $this->rutas->nivelPorId($tenantId, $nivelId);

        return $nivel === null ? null : [
            'nivel_id' => $nivel['nivel_id'],
            'ruta_id' => $nivel['ruta_id'],
            'ruta_nombre' => $nivel['ruta_nombre'],
            'orden' => $nivel['orden'],
            'puesto_id' => $nivel['puesto_id'],
            'nombre' => $nivel['nombre'],
            'experiencia_minima_anios' => $nivel['experiencia_minima_anios'],
        ];
    }

    /** @return array<string, mixed>|null */
    private function nivel(?int $nivelId, Ruta $ruta, bool $conExperiencia): ?array
    {
        $nivel = $nivelId === null ? null : $ruta->nivelPorId($nivelId);
        if ($nivel === null) {
            return null;
        }
        $datos = ['id' => (int) $nivel->id, 'orden' => $nivel->orden, 'puesto_id' => $nivel->puestoId, 'nombre' => $nivel->nombre];

        return $conExperiencia ? $datos + ['experiencia_minima_anios' => $nivel->experienciaMinimaAnios] : $datos;
    }
}
