<?php

use App\Modules\Desarrollo\Domain\Exceptions\EncuestaNoPermitida;
use App\Modules\Desarrollo\Domain\Services\ReglasDeEncuesta;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante as A;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion as E;

function motivoEncuesta(E $estado = E::Finalizada, A $asistencia = A::Presente, string $hoy = '2026-03-20', bool $ya = false, string $fin = '2026-03-10'): ?string
{
    return (new ReglasDeEncuesta)->motivoDeRechazo($estado, $asistencia, new DateTimeImmutable($fin), new DateTimeImmutable($hoy), $ya);
}

it('permite responder a quien estuvo presente en una actividad finalizada dentro de la ventana', function () {
    expect(motivoEncuesta())->toBeNull();
});

it('acepta el día 30 y rechaza el 31 tras la fecha de fin', function () {
    expect(motivoEncuesta(hoy: '2026-04-09'))->toBeNull()
        ->and(motivoEncuesta(hoy: '2026-04-10'))->toBe(EncuestaNoPermitida::VENTANA_CERRADA);
});

it('rechaza si la actividad no está finalizada', function (E $estado) {
    expect(motivoEncuesta($estado))->toBe(EncuestaNoPermitida::ACTIVIDAD_NO_FINALIZADA);
})->with([E::Programada, E::EnCurso, E::Cancelada]);

it('rechaza si el colaborador no estuvo presente', function (A $asistencia) {
    expect(motivoEncuesta(asistencia: $asistencia))->toBe(EncuestaNoPermitida::SIN_ASISTENCIA);
})->with([A::Ausente, A::Justificado, A::Pendiente]);

it('rechaza una segunda respuesta', function () {
    expect(motivoEncuesta(ya: true))->toBe(EncuestaNoPermitida::YA_RESPONDIDA);
});

it('calcula el último día de la ventana', function () {
    expect((new ReglasDeEncuesta)->vence(new DateTimeImmutable('2026-03-10'))->format('Y-m-d'))->toBe('2026-04-09');
});

it('lanza la excepción con el motivo como código', function () {
    try {
        (new ReglasDeEncuesta)->validar(E::Finalizada, A::Ausente, new DateTimeImmutable('2026-03-10'), new DateTimeImmutable('2026-03-20'), false);
        $this->fail('Debió lanzar EncuestaNoPermitida');
    } catch (EncuestaNoPermitida $e) {
        expect($e->codigoError())->toBe('sin_asistencia')->and($e->estadoHttp())->toBe(422);
    }
});

it('no depende de una fecha de activación: una actividad terminada hace 10 días ofrece la encuesta', function () {
    expect(motivoEncuesta(hoy: '2026-03-20', fin: '2026-03-10'))->toBeNull();
});
