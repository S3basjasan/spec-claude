<?php

use App\Modules\GestionPersonal\Domain\Services\DetectorDeCruces;
use App\Modules\GestionPersonal\Domain\Services\VerificadorDeEvaluacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\ResultadoDeEvaluacion;

function novedadDe(int $id, string $inicio, string $fin, string $tipo = 'Permiso', string $estado = 'Presentada'): array
{
    return ['id' => $id, 'tipo' => $tipo, 'estado' => $estado, 'inicio' => $inicio, 'fin' => $fin];
}

it('detecta los periodos que comparten al menos un día, con fechas inclusivas', function () {
    $detector = new DetectorDeCruces;
    $otras = [
        novedadDe(1, '2026-11-01', '2026-11-05'),
        novedadDe(2, '2026-11-05', '2026-11-08'),
        novedadDe(3, '2026-11-06', '2026-11-09'),
        novedadDe(4, '2026-10-20', '2026-10-31'),
    ];

    expect(array_column($detector->cruces('2026-11-05', '2026-11-05', $otras), 'id'))->toBe([1, 2])
        ->and($detector->cruces('2026-12-01', '2026-12-02', $otras))->toBe([])
        ->and(array_column($detector->cruces('2026-11-01', '2026-11-30', $otras, 2), 'id'))->toBe([1, 3]);
});

it('ignora ausencias, retiradas y rechazadas: solo cuentan solicitudes vivas o aprobadas', function () {
    $detector = new DetectorDeCruces;
    $otras = [
        novedadDe(1, '2026-11-01', '2026-11-05', 'Ausencia', 'Informada'),
        novedadDe(2, '2026-11-01', '2026-11-05', 'Permiso', 'Retirada'),
        novedadDe(3, '2026-11-01', '2026-11-05', 'Licencia', 'Rechazada'),
        novedadDe(4, '2026-11-01', '2026-11-05', 'Vacaciones', 'Aprobada'),
        novedadDe(5, '2026-11-01', '2026-11-05', 'Permiso', 'EnRevision'),
    ];

    expect(array_column($detector->cruces('2026-11-02', '2026-11-03', $otras), 'id'))->toBe([4, 5]);
});

function respuestaDeEvaluacion(array $cambios = []): array
{
    return ['estado' => 'Valida', 'contexto' => array_replace([
        'colaborador_id' => 7, 'puesto_id' => 21, 'requisitos' => ['Formación afín'], 'evaluada_en' => '2026-10-01T10:00:00Z', 'resultado' => 'Apto con brechas',
    ], $cambios)];
}

it('una evaluación válida identifica colaborador, puesto, requisitos, momento y resultado, sin umbrales', function () {
    $verificador = new VerificadorDeEvaluacion;
    $ahora = new DateTimeImmutable('2026-10-22T12:00:00Z');

    $valida = $verificador->verificar(respuestaDeEvaluacion(), 7, 21, $ahora);
    expect($valida['estado'])->toBe(ResultadoDeEvaluacion::Valida)->and($valida['contexto']['resultado'])->toBe('Apto con brechas');

    $conBrechas = $verificador->verificar(respuestaDeEvaluacion(['resultado' => 'No apto']), 7, 21, $ahora);
    expect($conBrechas['estado'])->toBe(ResultadoDeEvaluacion::Valida);
});

it('lo ausente, ajeno, incompleto o vencido es no verificable y nunca válido', function (array $cambios, ?int $colaborador, ?int $puesto) {
    $verificador = new VerificadorDeEvaluacion;
    $r = $verificador->verificar(respuestaDeEvaluacion($cambios), $colaborador ?? 7, $puesto ?? 21, new DateTimeImmutable('2026-10-22T12:00:00Z'));

    expect($r['estado'])->toBe(ResultadoDeEvaluacion::NoVerificable)->and($r['contexto'])->toBeNull()->and($r['motivo'])->toBeString();
})->with([
    'otro colaborador' => [[], 8, null],
    'otro puesto' => [[], null, 22],
    'puesto destino desconocido' => [[], null, 0],
    'sin requisitos' => [['requisitos' => []], null, null],
    'sin resultado' => [['resultado' => ' '], null, null],
    'sin momento' => [['evaluada_en' => null], null, null],
    'momento futuro' => [['evaluada_en' => '2027-01-01T00:00:00Z'], null, null],
    'vencida' => [['vigente_hasta' => '2026-10-22T11:00:00Z'], null, null],
]);

it('una evaluación no disponible o una respuesta ilegible no es una evaluación', function () {
    $verificador = new VerificadorDeEvaluacion;
    $ahora = new DateTimeImmutable('2026-10-22T12:00:00Z');

    expect($verificador->verificar(['estado' => 'NoDisponible', 'contexto' => null], 7, 21, $ahora)['estado'])->toBe(ResultadoDeEvaluacion::NoDisponible)
        ->and($verificador->verificar(['estado' => 'Apto'], 7, 21, $ahora)['estado'])->toBe(ResultadoDeEvaluacion::NoVerificable)
        ->and($verificador->verificar(['estado' => 'Valida'], 7, 21, $ahora)['estado'])->toBe(ResultadoDeEvaluacion::NoVerificable)
        ->and($verificador->verificar([], 7, 21, $ahora)['estado'])->toBe(ResultadoDeEvaluacion::NoVerificable);
});
