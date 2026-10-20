<?php

use App\Modules\Desarrollo\Domain\Exceptions\LugarInvalido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;

function accionParaReglas(string $inicio = '2026-03-10', string $fin = '2026-03-20', bool $cancelada = false, EstadoPlan $plan = EstadoPlan::Ejecutando): AccionDeActividad
{
    return new AccionDeActividad(1, 1, 'PLAN-0001', 'Plan', $plan, 'Taller', TipoAccion::Presencial, new DateTimeImmutable($inicio), new DateTimeImmutable($fin), 8.0, 'Ing. Vera', 20, $cancelada, [1], [10, 11]);
}

it('genera el código ACT-NNNN con cuatro dígitos', function () {
    expect(CodigoActividad::desde(1))->toBe('ACT-0001')
        ->and(CodigoActividad::desde(42))->toBe('ACT-0042')
        ->and(CodigoActividad::desde(12345))->toBe('ACT-12345');
});

it('calcula el estado a partir de la acción y de hoy', function (string $hoy, EstadoAccion $esperado) {
    expect((new ReglasDeActividad)->estado(accionParaReglas(), new DateTimeImmutable($hoy)))->toBe($esperado);
})->with([
    'antes del inicio' => ['2026-03-09', EstadoAccion::Programada],
    'el día de inicio' => ['2026-03-10', EstadoAccion::EnCurso],
    'a mitad' => ['2026-03-15', EstadoAccion::EnCurso],
    'el día de fin' => ['2026-03-20', EstadoAccion::EnCurso],
    'después del fin' => ['2026-03-21', EstadoAccion::Finalizada],
]);

it('una acción cancelada deja la actividad Cancelada sin importar las fechas', function () {
    expect((new ReglasDeActividad)->estado(accionParaReglas(cancelada: true), new DateTimeImmutable('2026-03-15')))->toBe(EstadoAccion::Cancelada);
});

it('exige URL http o https para Virtual y E-learning', function (TipoAccion $tipo) {
    $reglas = new ReglasDeActividad;
    $reglas->validarLugar($tipo, 'https://meet.ejemplo.edu.ec/sala');
    $reglas->validarLugar($tipo, 'http://ejemplo.com/curso?id=3');

    foreach (['sala 3', 'meet ejemplo', 'ftp://ejemplo.com/a', 'https://', 'javascript:alert(1)', 'https://a b.com'] as $malo) {
        expect(fn () => $reglas->validarLugar($tipo, $malo))->toThrow(LugarInvalido::class);
    }
})->with([TipoAccion::Virtual, TipoAccion::ELearning]);

it('acepta texto libre para los demás tipos', function (TipoAccion $tipo) {
    (new ReglasDeActividad)->validarLugar($tipo, 'Sala de capacitación, piso 3');
    expect(true)->toBeTrue();
})->with([TipoAccion::Presencial, TipoAccion::OJT, TipoAccion::Codesarrollo, TipoAccion::Mentoring]);

it('rechaza un lugar vacío o de más de 500 caracteres', function () {
    $reglas = new ReglasDeActividad;

    expect(fn () => $reglas->validarLugar(TipoAccion::Presencial, '   '))->toThrow(LugarInvalido::class)
        ->and(fn () => $reglas->validarLugar(TipoAccion::Presencial, str_repeat('a', 501)))->toThrow(LugarInvalido::class);
    $reglas->validarLugar(TipoAccion::Presencial, str_repeat('a', 500));
});

it('bloquea el registro según el estado de la actividad y del plan', function (EstadoAccion $estado, EstadoPlan $plan, ?string $motivo) {
    $reglas = new ReglasDeActividad;

    expect($reglas->motivoBloqueo($estado, $plan))->toBe($motivo)
        ->and($reglas->puedeRegistrar($estado, $plan))->toBe($motivo === null);
})->with([
    'en curso' => [EstadoAccion::EnCurso, EstadoPlan::Ejecutando, null],
    'finalizada' => [EstadoAccion::Finalizada, EstadoPlan::Ejecutando, null],
    'programada' => [EstadoAccion::Programada, EstadoPlan::Ejecutando, 'actividad_programada'],
    'cancelada' => [EstadoAccion::Cancelada, EstadoPlan::Ejecutando, 'actividad_cancelada'],
    'plan cerrado finalizada' => [EstadoAccion::Finalizada, EstadoPlan::Cerrado, 'plan_cerrado'],
    'plan cerrado cancelada' => [EstadoAccion::Cancelada, EstadoPlan::Cerrado, 'plan_cerrado'],
]);

it('permite editar mientras el plan no esté Cerrado', function () {
    $reglas = new ReglasDeActividad;

    expect($reglas->puedeEditar(EstadoPlan::Ejecutando))->toBeTrue()
        ->and($reglas->puedeEditar(EstadoPlan::Cerrado))->toBeFalse();
});

it('marca asistencia pendiente solo en una actividad Finalizada con inscritos sin registro', function (EstadoAccion $estado, int $inscritos, int $registrados, bool $esperado) {
    expect((new ReglasDeActividad)->asistenciaPendiente($estado, $inscritos, $registrados))->toBe($esperado);
})->with([
    'finalizada con pendientes' => [EstadoAccion::Finalizada, 5, 3, true],
    'finalizada completa' => [EstadoAccion::Finalizada, 5, 5, false],
    'finalizada sin inscritos' => [EstadoAccion::Finalizada, 0, 0, false],
    'en curso con pendientes' => [EstadoAccion::EnCurso, 5, 0, false],
    'cancelada con pendientes' => [EstadoAccion::Cancelada, 5, 0, false],
]);
