<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AvisosDTO;
use App\Modules\Desarrollo\Application\DTO\DnfDTO;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDnf;
use App\Modules\Desarrollo\Domain\Entities\Brecha;
use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorNoEvaluable;
use App\Modules\Desarrollo\Domain\Exceptions\DepartamentoNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\SinColaboradoresEvaluables;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera y guarda una DNF individual o por departamento (incluye las subunidades). Obtiene el perfil del puesto y los
 * niveles actuales por los puertos, omite las competencias inactivas y a los colaboradores no evaluables, calcula cada
 * brecha con el dominio y guarda todo en una sola transacción.
 */
final class GenerarDnf
{
    public function __construct(
        private readonly ColaboradoresEvaluablesInterface $evaluables,
        private readonly PerfilesDePuestoInterface $perfiles,
        private readonly NivelesActualesInterface $niveles,
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly DnfRepositoryInterface $repositorio,
        private readonly CalculadorDeBrecha $calculador,
        private readonly ArmadorDeDnf $armador,
    ) {}

    /**
     * @return array{dnf: DnfDTO, avisos: AvisosDTO}
     *
     * @throws ColaboradorNoEvaluable
     * @throws DepartamentoNoEncontrado
     * @throws SinColaboradoresEvaluables
     */
    public function ejecutar(int $tenantId, int $actorId, AlcanceDnf $alcance, ?int $colaboradorId, ?int $departamentoId): array
    {
        if ($alcance === AlcanceDnf::Individual) {
            $grupo = $this->consultar(fn () => $this->evaluables->evaluablesPorIds($tenantId, [(int) $colaboradorId]));
            $this->validarIndividual($grupo, (int) $colaboradorId);
        } else {
            if ($this->consultar(fn () => $this->nombres->unidades([(int) $departamentoId]), []) === []) {
                throw new DepartamentoNoEncontrado;
            }
            $grupo = $this->consultar(fn () => $this->evaluables->evaluablesDeUnidad($tenantId, (int) $departamentoId));
        }

        $excluidos = $grupo['excluidos'];
        [$resultados, $omitidas, $excluidosDePerfil] = $this->evaluar($tenantId, $grupo['evaluables']);
        $excluidos = [...$excluidos, ...$excluidosDePerfil];

        if ($resultados === []) {
            if ($alcance === AlcanceDnf::Individual) {
                throw new ColaboradorNoEvaluable($excluidosDePerfil[0]['motivo'] ?? 'SinPerfil');
            }
            throw new SinColaboradoresEvaluables;
        }

        $guardada = $this->repositorio->guardar(new Dnf(
            null, $tenantId, null, $alcance, $alcance === AlcanceDnf::Departamento ? $departamentoId : null,
            $omitidas, null, null, null, $actorId, null, $resultados,
        ));

        return [
            'dnf' => $this->armador->armar($guardada, false),
            'avisos' => new AvisosDTO($omitidas, $guardada->sinEvaluacion(), array_values($excluidos)),
        ];
    }

    /**
     * @param  array{evaluables: list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>, excluidos: list<array{colaborador_id: int, nombre: string, motivo: string}>}  $grupo
     */
    private function validarIndividual(array $grupo, int $colaboradorId): void
    {
        if ($grupo['evaluables'] !== []) {
            return;
        }
        foreach ($grupo['excluidos'] as $excluido) {
            if ($excluido['colaborador_id'] === $colaboradorId) {
                throw new ColaboradorNoEvaluable($excluido['motivo']);
            }
        }

        throw new ColaboradorNoEvaluable('NoExiste');
    }

    /**
     * Calcula las brechas de cada colaborador evaluable. El perfil se consulta una vez por puesto distinto y los
     * niveles actuales se piden por lote.
     *
     * @param  list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>  $evaluables
     * @return array{0: list<ResultadoColaborador>, 1: int, 2: list<array{colaborador_id: int, nombre: string, motivo: string}>}
     *                                                                                                                           resultados, competencias inactivas omitidas distintas, colaboradores excluidos por no tener perfil
     */
    private function evaluar(int $tenantId, array $evaluables): array
    {
        if ($evaluables === []) {
            return [[], 0, []];
        }

        $perfiles = [];
        foreach (array_unique(array_column($evaluables, 'puesto_id')) as $puestoId) {
            $perfiles[$puestoId] = $this->consultar(fn () => $this->perfiles->perfilDe($puestoId), null);
        }
        $niveles = $this->consultar(fn () => $this->niveles->nivelesDe($tenantId, array_column($evaluables, 'colaborador_id')), []);

        $resultados = [];
        $excluidos = [];
        $inactivas = [];
        foreach ($evaluables as $colaborador) {
            $perfil = $perfiles[$colaborador['puesto_id']] ?? null;
            $activas = array_values(array_filter($perfil['competencias'] ?? [], fn (array $c) => $c['activa']));
            foreach (($perfil['competencias'] ?? []) as $competencia) {
                if (! $competencia['activa']) {
                    $inactivas[$competencia['competencia_id']] = true;
                }
            }
            if ($activas === []) {
                $excluidos[] = ['colaborador_id' => $colaborador['colaborador_id'], 'nombre' => $colaborador['nombre'], 'motivo' => 'SinPerfil'];

                continue;
            }

            $resultados[] = new ResultadoColaborador(
                $colaborador['colaborador_id'],
                $colaborador['puesto_id'],
                array_map(fn (array $c): Brecha => $this->calculador->calcular(
                    $c['competencia_id'],
                    TipoCompetenciaDnf::tryFrom($c['tipo']) ?? TipoCompetenciaDnf::Especifica,
                    NivelGrado::from($c['nivel_requerido']),
                    isset($niveles[$colaborador['colaborador_id']][$c['competencia_id']])
                        ? NivelGrado::tryFrom($niveles[$colaborador['colaborador_id']][$c['competencia_id']])
                        : null,
                ), $activas),
            );
        }

        return [$resultados, count($inactivas), $excluidos];
    }

    /**
     * Si un puerto falla se trata como «sin datos» y se registra sin datos personales.
     *
     * @template T
     *
     * @param  callable(): T  $consulta
     * @param  T|null  $porDefecto
     * @return T
     */
    private function consultar(callable $consulta, mixed $porDefecto = ['evaluables' => [], 'excluidos' => []]): mixed
    {
        try {
            return $consulta();
        } catch (Throwable $e) {
            Log::warning('Un puerto de la DNF no respondió.', ['excepcion' => $e::class]);

            return $porDefecto;
        }
    }
}
