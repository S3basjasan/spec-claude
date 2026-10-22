<?php

use App\Modules\Desarrollo\Application\Ports\ExperienciaDelColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\OfertaFormativaInterface;
use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Application\Ports\PuestoActualDelColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\ValoresDeLaPersonaInterface;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePreparacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use Tests\Support\Desarrollo\PerfilesDePuestoFalsos;

/**
 * Helpers de la feature 020 (Adecuación a puesto futuro). Datos sintéticos; se apoyan en los de la feature 019 (rutas,
 * puestos y colaboradores vigentes). Las adecuaciones de preparación se guardan con el repositorio real.
 */

/**
 * Perfiles de puesto sintéticos (puesto_id => perfil) alineados con `puestosDeRutas()`: el 1 es el puesto actual
 * típico, el 2 y el 3 los objetivos, el 5 no exige nada y el 4 tiene una competencia inactiva.
 *
 * @return array<int, array<string, mixed>>
 */
function perfilesDeAdecuacion(): array
{
    $c = fn (int $id, string $grado, bool $activa = true) => [
        'competencia_id' => $id, 'codigo' => sprintf('COM-%03d', $id), 'nombre' => "Competencia {$id}", 'tipo' => 'Cardinal', 'activa' => $activa, 'nivel_requerido' => $grado,
    ];
    $p = fn (int $id, string $nombre, array $competencias) => ['puesto' => ['id' => $id, 'codigo' => sprintf('PU-%03d', $id), 'nombre' => $nombre], 'competencias' => $competencias];

    return [
        1 => $p(1, 'Asistente de datos', [$c(1, 'C'), $c(2, 'D')]),
        2 => $p(2, 'Analista de datos', [$c(1, 'B'), $c(2, 'C'), $c(3, 'C')]),
        3 => $p(3, 'Analista senior', [$c(1, 'A'), $c(2, 'C'), $c(3, 'B')]),
        4 => $p(4, 'Jefe de datos', [$c(1, 'A'), $c(9, 'B', false)]),
        5 => $p(5, 'Gerente de datos', []),
    ];
}

/**
 * Enlaza con dobles los puertos de la adecuación. Por defecto el colaborador 10 está en el puesto 1 con las competencias
 * 1 en C, 2 en C y 3 sin nivel, y tiene 3 años de experiencia; los colaboradores 10 a 13 tienen vínculo vigente.
 *
 * @param  array<string, mixed>  $o  niveles (colaborador => [competencia => nivel]), anios, puestoActual, perfiles, vigentes, departamentos
 */
function adecuacionFalsos(array $o = []): void
{
    rutasFalsos(['puestos' => $o['puestos'] ?? null, 'vigentes' => $o['vigentes'] ?? null, 'departamentos' => $o['departamentos'] ?? null] + ($o['rutas'] ?? []));
    departamentosActuales($o['unidades'] ?? [10 => 1, 11 => 1, 12 => 2, 13 => 2]);

    app()->instance(PerfilesDePuestoInterface::class, new PerfilesDePuestoFalsos($o['perfiles'] ?? perfilesDeAdecuacion()));

    $niveles = $o['niveles'] ?? [10 => [1 => 'C', 2 => 'C']];
    app()->instance(ValoresDeLaPersonaInterface::class, new class($niveles) implements ValoresDeLaPersonaInterface
    {
        /** @param  array<int, array<int, string>>  $niveles */
        public function __construct(private readonly array $niveles) {}

        public function nivelesDeCompetencias(int $tenantId, int $colaboradorId): array
        {
            return $this->niveles[$colaboradorId] ?? [];
        }
    });

    $anios = $o['anios'] ?? [10 => 3.0];
    app()->instance(ExperienciaDelColaboradorInterface::class, new class($anios) implements ExperienciaDelColaboradorInterface
    {
        /** @param  array<int, float|null>  $anios */
        public function __construct(private readonly array $anios) {}

        public function aniosDe(int $tenantId, array $colaboradorIds): array
        {
            $r = [];
            foreach ($colaboradorIds as $id) {
                $r[$id] = $this->anios[$id] ?? null;
            }

            return $r;
        }
    });

    $puestos = $o['puestoActual'] ?? [10 => 1, 11 => 1, 12 => 1, 13 => 1];
    app()->instance(PuestoActualDelColaboradorInterface::class, new class($puestos) implements PuestoActualDelColaboradorInterface
    {
        /** @param  array<int, int|null>  $puestos */
        public function __construct(private readonly array $puestos) {}

        public function puestoActualDe(int $tenantId, array $colaboradorIds, string $fecha): array
        {
            $r = [];
            foreach ($colaboradorIds as $id) {
                $r[$id] = $this->puestos[$id] ?? null;
            }

            return $r;
        }
    });
}

/**
 * Escenario base: el administrador actúa y el colaborador 10 está inscrito en una ruta de tres niveles (puestos 1, 2 y 3)
 * con nivel actual 1 y objetivo en el nivel 2 (puesto 2, experiencia mínima 1 año).
 *
 * @param  array<string, mixed>  $falsos  opciones de `adecuacionFalsos`
 * @return array{0: array{id: int, niveles: list<int>, codigo: string}, 1: RutaInscripcionModel}
 */
function escenarioDeAdecuacion(array $falsos = []): array
{
    actuarComoAdminDeTenant();
    adecuacionFalsos($falsos);
    $ruta = rutaEnBd([1, 2, 3], 'Vigente');
    $inscripcion = inscripcionEnBd($ruta['id'], 10, $ruta['niveles'][0], $ruta['niveles'][1]);

    return [$ruta, $inscripcion];
}

/** Actúa como colaborador con ficha y cuenta; devuelve el id de su ficha. Las competencias con oferta se indican por id. */
function actuarComoColaboradorDeAdecuacion(array $conOferta = [1]): int
{
    [, $fichaId] = colaboradorConCuentaDePlan();
    adecuacionFalsos(['vigentes' => [$fichaId => 'Yo', 11 => 'Luis Mora']]);
    app()->instance(OfertaFormativaInterface::class, new class($conOferta) implements OfertaFormativaInterface
    {
        /** @param  list<int>  $conOferta */
        public function __construct(private readonly array $conOferta) {}

        public function tieneOferta(int $competenciaId): bool
        {
            return in_array($competenciaId, $this->conOferta, true);
        }
    });

    return $fichaId;
}

/**
 * Guarda directamente una adecuación con el repositorio real, calculando cada resultado.
 *
 * @param  list<array{dim?: string, id?: int, objetivo?: string, valor?: string|null, actual?: string|null, anios?: array{0: float, 1: float|null}, tiempo?: int|null}>  $requisitos
 * @param  array<string, mixed>  $cabecera  colaborador, puesto, actual, inscripcion, nivel, experiencia, umbral, observaciones, tenant, actor
 */
function adecuacionEnBd(array $requisitos, array $cabecera = []): Adecuacion
{
    $calculador = new CalculadorDeResultado;
    $resultados = [];
    foreach ($requisitos as $r) {
        if (isset($r['anios'])) {
            $resultado = $calculador->paraExperiencia(null, $r['anios'][0], $r['anios'][1] ?? null);
        } else {
            $resultado = $calculador->paraGrado(
                DimensionDeAdecuacion::from($r['dim'] ?? 'Competencia'),
                $r['id'] ?? 1,
                'Competencia '.($r['id'] ?? 1),
                isset($r['actual']) ? NivelGrado::from($r['actual']) : null,
                NivelGrado::from($r['objetivo'] ?? 'B'),
                isset($r['valor']) ? NivelGrado::from($r['valor']) : null,
            );
        }
        $resultados[] = $resultado->esBrecha() ? $resultado->conTiempoDeCierre($r['tiempo'] ?? 6) : $resultado;
    }

    $umbral = $cabecera['umbral'] ?? 12;
    $guardada = app(AdecuacionesRepositoryInterface::class)->guardar(new Adecuacion(
        null,
        $cabecera['tenant'] ?? 1,
        null,
        $cabecera['colaborador'] ?? 10,
        $cabecera['actual'] ?? 1,
        $cabecera['puesto'] ?? 2,
        $cabecera['inscripcion'] ?? null,
        $cabecera['nivel'] ?? null,
        $cabecera['experiencia'] ?? null,
        (new CalculadorDePreparacion)->calcular($resultados, $umbral),
        $umbral,
        $cabecera['observaciones'] ?? null,
        $cabecera['actor'] ?? 1,
        null,
        $resultados,
    ));

    if (isset($cabecera['creada'])) {
        AdecuacionModel::query()->whereKey($guardada->id)->toBase()->update(['created_at' => $cabecera['creada']]);
    }

    return $guardada;
}

/** @return list<ResultadoDeAdecuacion> */
function resultadosDe(Adecuacion $a): array
{
    return $a->resultados;
}

function preparacionDe(string $valor): Preparacion
{
    return Preparacion::from($valor);
}
