<?php

use App\Modules\GestionPersonal\Application\Services\EvaluadorDeCierre;
use App\Modules\GestionPersonal\Application\Services\ValidadorDeRespaldos;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaEfectivaInvalida;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\CalculadorDeAgregados;
use App\Modules\GestionPersonal\Domain\Services\CuestionarioDeSalida;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeSalida;
use App\Modules\GestionPersonal\Domain\Services\RepresentacionParaAnalisis;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeEntrevista;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use App\Modules\GestionPersonal\Domain\ValueObjects\MotivoDeSalida;

function expedienteDePrueba(EstadoDeSalida $estado = EstadoDeSalida::Efectiva, array $tareas = [], string $cierre = 'Pendiente'): Expediente
{
    return new Expediente(
        1, 1, 10, 20, MotivoDeSalida::Renuncia, '2026-11-01', $estado, $cierre, null, null, 'Pendiente', 5, 1, [], 1, '2026-10-01 00:00:00',
        null, null, null, false, null, null, 0, 1, $tareas,
        ['id' => 1, 'estado' => EstadoDeEntrevista::Pendiente, 'cuestionario_version' => 1, 'presentada_en' => null, 'finalizada_en' => null, 'motivo_no_realizada' => null, 'no_realizada_por' => null, 'no_realizada_en' => null, 'version' => 1],
        [],
    );
}

function tareaDePrueba(string $tipo, string $estado, bool $evidencia = false, array $documentos = []): array
{
    return ['id' => 1, 'orden' => 1, 'tipo' => $tipo, 'nombre' => 'T', 'descripcion' => null, 'exige_evidencia' => $evidencia, 'estado' => $estado, 'completada_por' => null, 'completada_en' => null, 'documento_ids' => $documentos];
}

/** @param  'sin_respaldo'|'falla'  $modo  El repositorio de documentos no encuentra el respaldo o no responde. */
function evaluadorConDocumentos(string $modo = 'sin_respaldo'): EvaluadorDeCierre
{
    $documentos = Mockery::mock(DocumentoRepositoryInterface::class);
    $consulta = $documentos->shouldReceive('porId');
    $modo === 'falla' ? $consulta->andThrow(new RuntimeException('Documentos no responde')) : $consulta->andReturn(null);

    return new EvaluadorDeCierre(new ValidadorDeRespaldos($documentos));
}

it('el cuestionario v1 tiene 6 dimensiones, 8 categorías y 6 preguntas abiertas con escala 1–5', function () {
    $c = new CuestionarioDeSalida;

    expect($c->dimensiones())->toHaveCount(6)
        ->and($c->categorias())->toHaveCount(8)
        ->and($c->abiertas())->toHaveCount(6)
        ->and(array_keys(CuestionarioDeSalida::ESCALA))->toBe([1, 2, 3, 4, 5])
        ->and($c->preguntas())->toHaveCount(13)
        ->and(CuestionarioDeSalida::VERSION)->toBe(1);
});

it('el cuestionario normaliza: ausente no es cero, categorías sin repetir y texto intacto', function () {
    $c = new CuestionarioDeSalida;

    $n = $c->normalizar(['DIM-GENERAL' => 5, 'DIM-CARGA' => null, 'DIM-RECONOCIMIENTO' => ''], ['CAT-OTROS', 'CAT-OTROS', 'CAT-PERSONAL'], ['ABI-01' => ' texto ', 'ABI-02' => '', 'ABI-03' => null]);

    expect($n['valoraciones'])->toBe(['DIM-GENERAL' => 5])
        ->and($n['categorias'])->toBe(['CAT-OTROS', 'CAT-PERSONAL'])
        ->and($n['abiertas'])->toBe(['ABI-01' => ' texto ']);
});

it('el cuestionario rechaza valores fuera de escala, preguntas ajenas y textos demasiado largos', function (array $valoraciones, array $categorias, array $abiertas, string $campo) {
    try {
        (new CuestionarioDeSalida)->normalizar($valoraciones, $categorias, $abiertas);
        $errores = [];
    } catch (DatosInvalidos $e) {
        $errores = $e->errores;
    }

    expect(array_keys($errores))->toContain($campo);
})->with([
    'cero' => [['DIM-GENERAL' => 0], [], [], 'valoraciones.DIM-GENERAL'],
    'seis' => [['DIM-GENERAL' => 6], [], [], 'valoraciones.DIM-GENERAL'],
    'decimal' => [['DIM-GENERAL' => 2.5], [], [], 'valoraciones.DIM-GENERAL'],
    'texto' => [['DIM-GENERAL' => '3'], [], [], 'valoraciones.DIM-GENERAL'],
    'dimension ajena' => [['DIM-X' => 3], [], [], 'valoraciones.DIM-X'],
    'categoria ajena' => [[], ['CAT-X'], [], 'categorias'],
    'abierta ajena' => [[], [], ['ABI-9' => 'x'], 'abiertas.ABI-9'],
    'abierta larga' => [[], [], ['ABI-01' => str_repeat('a', 2001)], 'abiertas.ABI-01'],
    'abierta no texto' => [[], [], ['ABI-01' => 5], 'abiertas.ABI-01'],
]);

it('las reglas de salida aceptan hoy y fechas futuras posteriores al inicio y rechazan el resto', function () {
    $r = new ReglasDeSalida;

    expect($r->verificar('Renuncia', '2026-10-26', '2026-10-26', '2026-01-01'))->toBe(MotivoDeSalida::Renuncia)
        ->and($r->verificar('Jubilacion', '2027-01-01', '2026-10-26', '2026-01-01'))->toBe(MotivoDeSalida::Jubilacion);

    expect(fn () => $r->verificar('Renuncia', '2026-10-25', '2026-10-26', '2026-01-01'))->toThrow(FechaEfectivaInvalida::class)
        ->and(fn () => $r->verificar('Renuncia', '2026-11-01', '2026-10-26', '2026-11-01'))->toThrow(FechaEfectivaInvalida::class)
        ->and(fn () => $r->verificar('Despido', '2026-11-01', '2026-10-26', '2026-01-01'))->toThrow(DatosInvalidos::class)
        ->and(fn () => $r->verificar('Renuncia', '2026-02-30', '2026-10-26', '2026-01-01'))->toThrow(DatosInvalidos::class)
        ->and(fn () => $r->verificar('Renuncia', null, '2026-10-26', '2026-01-01'))->toThrow(DatosInvalidos::class);
});

it('el cierre se bloquea si la salida no es efectiva, hay tareas pendientes o la entrevista no está resuelta', function () {
    $evaluador = evaluadorConDocumentos();

    expect($evaluador->bloqueos(expedienteDePrueba(EstadoDeSalida::Programada, [tareaDePrueba('Plantilla', 'Completada'), tareaDePrueba('Entrevista', 'Completada')])))->toBe(['SALIDA_NO_EFECTIVA'])
        ->and($evaluador->bloqueos(expedienteDePrueba(EstadoDeSalida::Efectiva, [tareaDePrueba('Plantilla', 'Pendiente'), tareaDePrueba('Entrevista', 'Pendiente')])))->toBe(['TAREAS_PENDIENTES', 'ENTREVISTA_SIN_RESOLVER'])
        ->and($evaluador->bloqueos(expedienteDePrueba(EstadoDeSalida::Efectiva, [tareaDePrueba('Plantilla', 'Completada'), tareaDePrueba('Entrevista', 'Completada')])))->toBe([])
        ->and($evaluador->bloqueos(expedienteDePrueba(EstadoDeSalida::Efectiva, [], 'Cerrado')))->toBe(['YA_CERRADO']);
});

it('una evidencia no verificable o sin respaldos disponibles cuenta como incumplida, nunca como cumplida', function () {
    $tarea = tareaDePrueba('Plantilla', 'Completada', true, [7]);
    $expediente = expedienteDePrueba(EstadoDeSalida::Efectiva, [$tarea, tareaDePrueba('Entrevista', 'Completada')]);

    expect(evaluadorConDocumentos()->bloqueos($expediente))->toBe(['EVIDENCIA_NO_DISPONIBLE'])
        ->and(evaluadorConDocumentos('falla')->bloqueos($expediente))->toBe(['EVIDENCIA_NO_DISPONIBLE']);
});

it('los agregados se ocultan bajo el mínimo y calculan promedios deterministas desde el mínimo', function () {
    $c = new CalculadorDeAgregados(new CuestionarioDeSalida);
    $respuestas = [];
    foreach ([1, 2, 3, 4, 5] as $i => $valor) {
        $respuestas[] = ['entrevista_id' => $i + 1, 'pregunta_id' => 'DIM-GENERAL', 'tipo' => 'valoracion', 'valor' => $valor];
        $respuestas[] = ['entrevista_id' => $i + 1, 'pregunta_id' => 'CAT-CARGA', 'tipo' => 'categoria', 'valor' => null];
    }

    expect($c->calcular(4, $respuestas, 5)['oculto'])->toBeTrue()
        ->and($c->calcular(4, $respuestas, 5)['dimensiones'])->toBe([]);

    $visible = $c->calcular(5, $respuestas, 5);
    $general = collect($visible['dimensiones'])->firstWhere('dimension', 'Experiencia general');
    $carga = collect($visible['categorias'])->firstWhere('categoria', 'Carga laboral');
    expect($visible['oculto'])->toBeFalse()
        ->and($general['promedio'])->toEqual(3)
        ->and($general['distribucion'])->toBe([1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1])
        ->and($carga['menciones'])->toBe(5)
        ->and($c->calcular(5, $respuestas, 5))->toBe($visible);
});

it('la representación para análisis conserva procedencia, ausencias y versión, sin datos identificativos', function () {
    $r = new RepresentacionParaAnalisis(new CuestionarioDeSalida);

    $e = $r->construir(['id' => 9, 'cuestionario_version' => 1], 'FinContrato', [
        ['pregunta_id' => 'DIM-GENERAL', 'tipo' => 'valoracion', 'dimension_o_categoria' => 'Experiencia general', 'valor' => 4, 'texto' => null],
        ['pregunta_id' => 'ABI-02', 'tipo' => 'abierta', 'dimension_o_categoria' => 'ABI-02', 'valor' => null, 'texto' => 'Buen equipo'],
    ], 'ref-tecnica');

    $general = collect($e['cuantitativo'])->firstWhere('pregunta_id', 'DIM-GENERAL');
    $carga = collect($e['cuantitativo'])->firstWhere('pregunta_id', 'DIM-CARGA');
    expect($e['contexto'])->toBe(['referencia' => 'ref-tecnica', 'cuestionario_version' => 1, 'tipo_de_desvinculacion' => 'FinContrato'])
        ->and($general['valor'])->toBe(4)
        ->and($general['procedencia'])->toBe('declarado_por_colaborador')
        ->and($carga['valor'])->toBeNull()
        ->and($e['control']['respondidas'])->toBe(['DIM-GENERAL', 'ABI-02'])
        ->and($e['control']['no_respondidas'])->toContain('DIM-CARGA', 'CATEGORIAS', 'ABI-01')
        ->and(collect($e['cualitativo']['abiertas'])->firstWhere('pregunta_id', 'ABI-02')['respuesta'])->toBe('Buen equipo');
});
