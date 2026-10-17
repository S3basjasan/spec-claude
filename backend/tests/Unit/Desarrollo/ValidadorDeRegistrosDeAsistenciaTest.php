<?php

use App\Modules\Desarrollo\Domain\Exceptions\RegistroDeAsistenciaInvalido;
use App\Modules\Desarrollo\Domain\Services\ValidadorDeRegistrosDeAsistencia;

/** Devuelve los errores por campo que produce el validador (o un arreglo vacío si el lote es válido). */
function erroresDeAsistencia(array $registros, array $inscritos = [10, 11, 12]): array
{
    try {
        (new ValidadorDeRegistrosDeAsistencia)->validar($registros, $inscritos);
    } catch (RegistroDeAsistenciaInvalido $e) {
        return $e->errores();
    }

    return [];
}

it('acepta un lote válido con las cuatro asistencias, calificación y observaciones', function () {
    expect(erroresDeAsistencia([
        ['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 5, 'observaciones' => 'Excelente'],
        ['colaborador_id' => 11, 'asistencia' => 'Ausente', 'observaciones' => 'Avisó tarde'],
        ['colaborador_id' => 12, 'asistencia' => 'Justificado'],
    ], [10, 11, 12]))->toBe([])
        ->and(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Pendiente']]))->toBe([]);
});

it('rechaza un lote vacío', function () {
    expect(erroresDeAsistencia([]))->toHaveKey('registros');
});

it('rechaza una asistencia fuera de las cuatro permitidas', function () {
    expect(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Tarde']]))->toHaveKey('registros.0.asistencia')
        ->and(erroresDeAsistencia([['colaborador_id' => 10]]))->toHaveKey('registros.0.asistencia');
});

it('rechaza una calificación que no es un entero de 1 a 5', function (mixed $calificacion) {
    expect(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => $calificacion]]))
        ->toHaveKey('registros.0.calificacion');
})->with([0, 6, -1, 4.5, '4', 'cuatro']);

it('acepta calificaciones de 1 a 5 solo con asistencia Presente', function (int $calificacion) {
    expect(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => $calificacion]]))->toBe([]);
})->with([1, 2, 3, 4, 5]);

it('rechaza calificar a quien no está Presente', function (string $asistencia) {
    expect(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => $asistencia, 'calificacion' => 3]]))
        ->toHaveKey('registros.0.calificacion');
})->with(['Ausente', 'Justificado', 'Pendiente']);

it('rechaza observaciones u observaciones largas con asistencia Pendiente', function () {
    expect(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Pendiente', 'observaciones' => 'Nota']]))->toHaveKey('registros.0.observaciones')
        ->and(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Pendiente', 'observaciones' => '']]))->toBe([]);
});

it('rechaza observaciones de más de 1 000 caracteres', function () {
    expect(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Presente', 'observaciones' => str_repeat('a', 1001)]]))->toHaveKey('registros.0.observaciones')
        ->and(erroresDeAsistencia([['colaborador_id' => 10, 'asistencia' => 'Presente', 'observaciones' => str_repeat('a', 1000)]]))->toBe([]);
});

it('rechaza colaboradores repetidos y no inscritos', function () {
    $errores = erroresDeAsistencia([
        ['colaborador_id' => 10, 'asistencia' => 'Presente'],
        ['colaborador_id' => 10, 'asistencia' => 'Ausente'],
        ['colaborador_id' => 99, 'asistencia' => 'Presente'],
    ]);

    expect($errores)->toHaveKey('registros.1.colaborador_id')->toHaveKey('registros.2.colaborador_id');
});

it('rechaza un colaborador que no es un entero', function () {
    expect(erroresDeAsistencia([['colaborador_id' => 'diez', 'asistencia' => 'Presente']]))->toHaveKey('registros.0.colaborador_id');
});
