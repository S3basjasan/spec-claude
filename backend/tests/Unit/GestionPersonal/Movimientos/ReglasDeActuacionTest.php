<?php

use App\Modules\GestionPersonal\Domain\Exceptions\AnticipacionInsuficiente;
use App\Modules\GestionPersonal\Domain\Exceptions\AusenciaFutura;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoRequerido;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaPasada;
use App\Modules\GestionPersonal\Domain\Exceptions\JustificacionRequerida;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeActuacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion as T;

beforeEach(fn () => $this->reglas = new ReglasDeActuacion);

const HOY = '2026-10-22';

it('la anticipación se cuenta en días calendario desde la fecha de presentación', function () {
    $datos = fn (string $inicio) => ['fecha_inicio' => $inicio, 'fecha_fin' => $inicio, 'motivo' => 'x'];

    expect(fn () => $this->reglas->verificar(T::Vacaciones, $datos('2026-10-26'), ['anticipacion_dias' => 5], HOY, 0))->toThrow(AnticipacionInsuficiente::class)
        ->and(fn () => $this->reglas->verificar(T::Vacaciones, $datos('2026-10-27'), ['anticipacion_dias' => 5], HOY, 0))->not->toThrow(Throwable::class);

    try {
        $this->reglas->verificar(T::Vacaciones, $datos('2026-10-23'), ['anticipacion_dias' => 5], HOY, 0);
    } catch (AnticipacionInsuficiente $e) {
        expect($e->getMessage())->toContain('5 días')->toContain('2026-10-27');
    }
});

it('anticipación cero admite el día actual y nunca una fecha pasada', function () {
    $datos = fn (string $inicio) => ['fecha_inicio' => $inicio, 'fecha_fin' => $inicio];

    expect(fn () => $this->reglas->verificar(T::Permiso, $datos(HOY), ['anticipacion_dias' => 0], HOY, 0))->not->toThrow(Throwable::class)
        ->and(fn () => $this->reglas->verificar(T::Permiso, $datos('2026-10-21'), ['anticipacion_dias' => 0], HOY, 0))->toThrow(FechaPasada::class)
        ->and(fn () => $this->reglas->verificar(T::Permiso, $datos('2026-10-21'), [], HOY, 0))->toThrow(FechaPasada::class);
});

it('un movimiento mide la anticipación contra la fecha efectiva y exige siempre la justificación', function () {
    $datos = ['fecha_efectiva' => '2026-10-25', 'motivo' => 'Desempeño'];

    expect(fn () => $this->reglas->verificar(T::Ascenso, $datos, ['anticipacion_dias' => 5], HOY, 0))->toThrow(AnticipacionInsuficiente::class)
        ->and(fn () => $this->reglas->verificar(T::Ascenso, $datos, ['anticipacion_dias' => 3], HOY, 0))->not->toThrow(Throwable::class)
        ->and(fn () => $this->reglas->verificar(T::Traslado, [...$datos, 'motivo' => ' '], [], HOY, 0))->toThrow(DatosInvalidos::class)
        ->and(fn () => $this->reglas->verificar(T::Traslado, $datos, ['documento_adicional_obligatorio' => true], HOY, 0))->toThrow(DocumentoRequerido::class)
        ->and(fn () => $this->reglas->verificar(T::Traslado, $datos, ['documento_adicional_obligatorio' => true], HOY, 1))->not->toThrow(Throwable::class);
});

it('una ausencia no usa la anticipación y no puede ser futura', function () {
    $ausencia = fn (string $fin) => ['fecha_inicio' => '2026-01-01', 'fecha_fin' => $fin];

    expect(fn () => $this->reglas->verificar(T::Ausencia, $ausencia(HOY), ['anticipacion_dias' => 99], HOY, 0))->not->toThrow(Throwable::class)
        ->and(fn () => $this->reglas->verificar(T::Ausencia, $ausencia('2026-10-23'), [], HOY, 0))->toThrow(AusenciaFutura::class);
});

it('exige justificación y documento solo cuando la política lo pide, y trata lo que falta como incumplido', function () {
    $datos = ['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-02', 'motivo' => ''];

    expect(fn () => $this->reglas->verificar(T::Licencia, $datos, ['justificacion_obligatoria' => true], HOY, 0))->toThrow(JustificacionRequerida::class)
        ->and(fn () => $this->reglas->verificar(T::Licencia, [...$datos, 'motivo' => 'Médica'], ['documento_obligatorio' => true], HOY, 0))->toThrow(DocumentoRequerido::class)
        ->and(fn () => $this->reglas->verificar(T::Licencia, $datos, ['justificacion_obligatoria' => false, 'documento_obligatorio' => false], HOY, 0))->not->toThrow(Throwable::class)
        ->and(fn () => $this->reglas->verificar(T::Licencia, $datos, [], HOY, 0))->not->toThrow(Throwable::class);
});

it('valida el formato y el rango de las fechas y el orden del periodo', function () {
    $errores = fn (array $d) => $this->reglas->errores(T::Permiso, $d, HOY);

    expect($errores(['fecha_inicio' => '2026-02-30', 'fecha_fin' => '2026-03-01']))->toHaveKey('fecha_inicio')
        ->and($errores(['fecha_inicio' => '2026-11-05', 'fecha_fin' => '2026-11-01']))->toHaveKey('fecha_fin')
        ->and($errores(['fecha_inicio' => '1800-01-01', 'fecha_fin' => '1800-01-02']))->toHaveKey('fecha_inicio')
        ->and($errores(['fecha_inicio' => '2040-01-01', 'fecha_fin' => '2040-01-02']))->toHaveKey('fecha_inicio')
        ->and($errores(['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01']))->toBe([])
        ->and($errores(['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01', 'motivo' => str_repeat('a', 1001)]))->toHaveKey('motivo');
});
