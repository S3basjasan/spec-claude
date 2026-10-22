<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\DTO\DiagnosticoDTO;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\ExperienciaDelColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Application\Ports\PuestoActualDelColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Ports\RutasParaAdecuacionInterface;
use App\Modules\Desarrollo\Application\Ports\ValoresDeLaPersonaInterface;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorNoElegible;
use App\Modules\Desarrollo\Domain\Exceptions\ErrorDeAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\ObjetivoInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\SinRequisitosEvaluables;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Arma el diagnóstico de una adecuación: lee el perfil del puesto objetivo y el valor de la persona por puertos, omite
 * lo inactivo y calcula cada requisito con `CalculadorDeResultado`. El servidor siempre calcula; nunca recibe valores.
 */
final class ArmadorDeDiagnostico
{
    public function __construct(
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly RutasParaAdecuacionInterface $rutas,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly PerfilesDePuestoInterface $perfiles,
        private readonly ValoresDeLaPersonaInterface $valores,
        private readonly ExperienciaDelColaboradorInterface $experiencia,
        private readonly PuestoActualDelColaboradorInterface $puestoActual,
        private readonly CalculadorDeResultado $calculador,
    ) {}

    /**
     * @param  float|null  $experienciaRequerida  solo se usa con puesto directo; con inscripción manda la del nivel
     *
     * @throws ErrorDeAdecuacion
     */
    public function armar(int $tenantId, int $colaboradorId, ?int $inscripcionId, ?int $puestoObjetivoId, ?float $experienciaRequerida): DiagnosticoDTO
    {
        if ($inscripcionId === null && $puestoObjetivoId === null) {
            throw ObjetivoInvalido::noDefinido();
        }
        if ($inscripcionId !== null && $puestoObjetivoId !== null) {
            throw ObjetivoInvalido::ambiguo();
        }

        $fuentes = [];
        $hoy = CarbonImmutable::today()->toDateString();
        $this->exigirElegible($tenantId, $colaboradorId, $hoy);

        $nivelObjetivoId = null;
        if ($inscripcionId !== null) {
            [$puestoObjetivoId, $nivelObjetivoId, $experienciaRequerida] = $this->objetivoDeLaInscripcion($tenantId, $colaboradorId, $inscripcionId);
        } else {
            $this->exigirPuestoActivo((int) $puestoObjetivoId);
        }

        $perfil = $this->perfil((int) $puestoObjetivoId);
        $puestoActualId = $this->puestoActual->puestoActualDe($tenantId, [$colaboradorId], $hoy)[$colaboradorId] ?? null;
        $requeridosActuales = $this->requeridosDelPuestoActual($puestoActualId, $fuentes);
        $niveles = $this->nivelesDeLaPersona($tenantId, $colaboradorId, $fuentes);

        $resultados = [];
        $omitidas = 0;
        foreach ($perfil['competencias'] as $c) {
            if (! $c['activa']) {
                $omitidas++;

                continue;
            }
            $resultados[] = $this->calculador->paraGrado(
                DimensionDeAdecuacion::Competencia,
                (int) $c['competencia_id'],
                (string) $c['nombre'],
                isset($requeridosActuales[$c['competencia_id']]) ? NivelGrado::tryFrom($requeridosActuales[$c['competencia_id']]) : null,
                NivelGrado::from((string) $c['nivel_requerido']),
                isset($niveles[$c['competencia_id']]) ? NivelGrado::tryFrom($niveles[$c['competencia_id']]) : null,
            );
        }

        $aniosPersona = null;
        if ($experienciaRequerida !== null) {
            $aniosPersona = $this->experiencia->aniosDe($tenantId, [$colaboradorId])[$colaboradorId] ?? null;
            $resultados[] = $this->calculador->paraExperiencia(null, $experienciaRequerida, $aniosPersona);
        }

        if ($resultados === []) {
            throw new SinRequisitosEvaluables;
        }

        return new DiagnosticoDTO(
            $colaboradorId,
            $puestoActualId,
            (int) $puestoObjetivoId,
            (string) $perfil['puesto']['nombre'],
            $inscripcionId,
            $nivelObjetivoId,
            $experienciaRequerida,
            $aniosPersona,
            $this->tipoDeCambio($inscripcionId, $puestoActualId, (int) $puestoObjetivoId),
            $resultados,
            $this->avisos($resultados, $omitidas, $experienciaRequerida, $puestoActualId, (int) $puestoObjetivoId),
            array_values(array_unique($fuentes)),
        );
    }

    private function exigirElegible(int $tenantId, int $colaboradorId, string $hoy): void
    {
        try {
            $vigentes = $this->colaboradores->vigentesPorIds($tenantId, [$colaboradorId], $hoy);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaAdecuacion;
        }

        if (! in_array($colaboradorId, $vigentes, true)) {
            throw new ColaboradorNoElegible;
        }
    }

    private function exigirPuestoActivo(int $puestoId): void
    {
        try {
            $activo = $this->puestos->estaActivo($puestoId);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaAdecuacion;
        }

        if (! $activo) {
            throw ObjetivoInvalido::puestoNoDisponible();
        }
    }

    /** @return array{0: int, 1: int, 2: float|null} puesto, nivel y experiencia mínima del nivel objetivo */
    private function objetivoDeLaInscripcion(int $tenantId, int $colaboradorId, int $inscripcionId): array
    {
        try {
            $inscripciones = $this->rutas->inscripcionesActivas($tenantId, $colaboradorId);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaAdecuacion;
        }

        foreach ($inscripciones as $i) {
            if ((int) $i['inscripcion_id'] !== $inscripcionId) {
                continue;
            }
            $objetivo = $i['nivel_objetivo'] ?? null;
            if ($objetivo === null) {
                throw ObjetivoInvalido::noDefinido();
            }
            $this->exigirPuestoActivo((int) $objetivo['puesto_id']);
            $minima = $objetivo['experiencia_minima_anios'] ?? null;

            return [(int) $objetivo['puesto_id'], (int) $objetivo['id'], $minima === null ? null : (float) $minima];
        }

        throw ObjetivoInvalido::inscripcionAjena();
    }

    /** @return array{puesto: array{id: int, codigo: string, nombre: string}, competencias: list<array{competencia_id: int, codigo: string, nombre: string, tipo: string, activa: bool, nivel_requerido: string}>} */
    private function perfil(int $puestoId): array
    {
        try {
            $perfil = $this->perfiles->perfilDe($puestoId);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaAdecuacion;
        }

        if ($perfil === null) {
            throw ObjetivoInvalido::puestoNoDisponible();
        }

        return $perfil;
    }

    /**
     * @param  list<string>  $fuentes
     * @return array<int, string> competencia_id => nivel requerido por el puesto actual
     */
    private function requeridosDelPuestoActual(?int $puestoActualId, array &$fuentes): array
    {
        if ($puestoActualId === null) {
            return [];
        }

        try {
            $perfil = $this->perfiles->perfilDe($puestoActualId);
        } catch (Throwable) {
            $fuentes[] = 'estructura';

            return [];
        }

        $salida = [];
        foreach ($perfil['competencias'] ?? [] as $c) {
            $salida[(int) $c['competencia_id']] = (string) $c['nivel_requerido'];
        }

        return $salida;
    }

    /**
     * @param  list<string>  $fuentes
     * @return array<int, string>
     */
    private function nivelesDeLaPersona(int $tenantId, int $colaboradorId, array &$fuentes): array
    {
        try {
            return $this->valores->nivelesDeCompetencias($tenantId, $colaboradorId);
        } catch (Throwable) {
            $fuentes[] = 'dnf';

            return [];
        }
    }

    private function tipoDeCambio(?int $inscripcionId, ?int $puestoActualId, int $puestoObjetivoId): string
    {
        return match (true) {
            $puestoActualId !== null && $puestoActualId === $puestoObjetivoId => 'MismoPuesto',
            $inscripcionId !== null => 'Promocion',
            default => 'PuestoDirecto',
        };
    }

    /**
     * @param  list<ResultadoDeAdecuacion>  $resultados
     * @return list<array{tipo: string, mensaje: string, cantidad: int}>
     */
    private function avisos(array $resultados, int $omitidas, ?float $experienciaRequerida, ?int $puestoActualId, int $puestoObjetivoId): array
    {
        $avisos = [];
        $sinEvaluar = count(array_filter($resultados, fn (ResultadoDeAdecuacion $r) => $r->estado === EstadoDeResultado::SinEvaluacion));
        if ($sinEvaluar > 0) {
            $avisos[] = ['tipo' => 'sin_evaluacion', 'mensaje' => "Hay {$sinEvaluar} requisito(s) sin evaluación: la persona no tiene valor registrado.", 'cantidad' => $sinEvaluar];
        }
        if ($omitidas > 0) {
            $avisos[] = ['tipo' => 'inactivos_omitidos', 'mensaje' => "Se omitieron {$omitidas} competencia(s) inactivas del perfil del puesto.", 'cantidad' => $omitidas];
        }
        $avisos[] = ['tipo' => 'conocimientos_desactivados', 'mensaje' => 'Los conocimientos no se evalúan: Estructura Empresarial no les asigna grado.', 'cantidad' => 0];
        if ($experienciaRequerida === null) {
            $avisos[] = ['tipo' => 'experiencia_no_evaluada', 'mensaje' => 'La experiencia no se evalúa porque no se indicó la experiencia requerida.', 'cantidad' => 0];
        }
        if ($puestoActualId !== null && $puestoActualId === $puestoObjetivoId) {
            $avisos[] = ['tipo' => 'mismo_puesto', 'mensaje' => 'El puesto objetivo es el puesto actual de la persona.', 'cantidad' => 0];
        }

        return $avisos;
    }
}
