<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Domain\Entities\Brecha;
use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use Tests\Support\Desarrollo\ColaboradoresEvaluablesFalsos;
use Tests\Support\Desarrollo\NivelesActualesFalsos;
use Tests\Support\Desarrollo\NombresDeReferenciaFalsos;
use Tests\Support\Desarrollo\PerfilesDePuestoFalsos;
use Tests\Support\Desarrollo\PlanDeFormacionFalso;

/**
 * Helpers de la feature 006 (DNF). Datos sintéticos.
 */

/** Competencia del perfil de un puesto: [id, tipo, nivel requerido, activa]. */
function competenciaDePerfil(int $id, string $requerido = 'A', string $tipo = 'Cardinal', bool $activa = true): array
{
    return [
        'competencia_id' => $id,
        'codigo' => sprintf('COM-%03d', $id),
        'nombre' => "Competencia {$id}",
        'tipo' => $tipo,
        'activa' => $activa,
        'nivel_requerido' => $requerido,
    ];
}

/** Perfil de un puesto con las competencias indicadas (resultado de `competenciaDePerfil`). */
function perfilDePuesto(int $puestoId, array $competencias): array
{
    return [
        'puesto' => ['id' => $puestoId, 'codigo' => sprintf('PU-%03d', $puestoId), 'nombre' => "Puesto {$puestoId}"],
        'competencias' => array_values($competencias),
    ];
}

/** Colaborador evaluable para el puerto falso. */
function evaluableDe(int $colaboradorId, int $puestoId = 1, ?int $unidadId = 1): array
{
    return ['colaborador_id' => $colaboradorId, 'nombre' => "Colaborador {$colaboradorId}", 'puesto_id' => $puestoId, 'unidad_id' => $unidadId];
}

/**
 * Enlaza en el contenedor los puertos de la DNF con dobles y devuelve los dobles.
 *
 * @param  array<string, mixed>  $o  evaluables, excluidos, subunidades, departamentos, perfiles, niveles, plan, inactivas
 * @return array{evaluables: ColaboradoresEvaluablesFalsos, niveles: NivelesActualesFalsos, perfiles: PerfilesDePuestoFalsos, plan: PlanDeFormacionFalso}
 */
function dnfFalsos(array $o = []): array
{
    $evaluables = new ColaboradoresEvaluablesFalsos(
        $o['evaluables'] ?? [],
        $o['excluidos'] ?? [],
        $o['subunidades'] ?? [],
        $o['departamentos'] ?? [],
    );
    $niveles = new NivelesActualesFalsos($o['niveles'] ?? []);
    $perfiles = new PerfilesDePuestoFalsos($o['perfiles'] ?? []);
    $plan = $o['plan'] ?? new PlanDeFormacionFalso;

    app()->instance(ColaboradoresEvaluablesInterface::class, $evaluables);
    app()->instance(NivelesActualesInterface::class, $niveles);
    app()->instance(PerfilesDePuestoInterface::class, $perfiles);
    app()->instance(PlanDeFormacionInterface::class, $plan);
    app()->instance(NombresDeReferenciaInterface::class, new NombresDeReferenciaFalsos($o['inactivas'] ?? []));

    return ['evaluables' => $evaluables, 'niveles' => $niveles, 'perfiles' => $perfiles, 'plan' => $plan];
}

/** Brecha calculada por el dominio. */
function brechaDe(int $competenciaId, string $requerido, ?string $actual, string $tipo = 'Cardinal'): Brecha
{
    return (new CalculadorDeBrecha)->calcular(
        $competenciaId,
        TipoCompetenciaDnf::from($tipo),
        NivelGrado::from($requerido),
        $actual === null ? null : NivelGrado::from($actual),
    );
}

/**
 * Guarda una DNF por el repositorio.
 *
 * @param  array<int, list<array{0: int, 1: string, 2: string|null, 3?: string}>>  $porColaborador  colaborador_id => [[competencia, requerido, actual, tipo?]]
 */
function dnfDeEjemplo(array $porColaborador, string $alcance = 'Individual', ?int $departamentoId = null, int $tenantId = 1, int $creadoPor = 1, int $puestoId = 1, int $omitidas = 0): Dnf
{
    $resultados = [];
    foreach ($porColaborador as $colaboradorId => $filas) {
        $resultados[] = new ResultadoColaborador(
            $colaboradorId,
            $puestoId,
            array_map(fn (array $f) => brechaDe($f[0], $f[1], $f[2], $f[3] ?? 'Cardinal'), $filas),
        );
    }

    return app(DnfRepositoryInterface::class)->guardar(new Dnf(
        null, $tenantId, null, AlcanceDnf::from($alcance), $departamentoId, $omitidas, null, null, null, $creadoPor, null, $resultados,
    ));
}
