<?php

use App\Modules\Desarrollo\Application\Ports\FuenteDePlanesInterface;
use App\Modules\Desarrollo\Application\Services\RecolectorDeReportes;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIndicadores;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosDeReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Eva']]);
    departamentosActuales([10 => 1, 11 => 1]);
    $this->recolectar = fn (int $tenant = 1) => app(RecolectorDeReportes::class)->recolectar($tenant, 1);
    $this->filtro = fn (?int $departamento = null, ?string $tipo = null) => new FiltroReportes(
        new PeriodoReporte(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31')), $departamento, $tipo,
    );
});

it('reúne los datos de todas las fuentes con una lectura cada una', function () {
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Ausente']);
    encuestaDe($a['actividad'], 10, 5);
    impactoDeAccion($a['accion'], 1000);
    progresoMicro(microDe()->id, 10, 'Completado');
    dnfEvaluada('2026-01-10', 10, 3, 'ConBrecha', 'D');

    $d = ($this->recolectar)();

    expect($d)->toBeInstanceOf(DatosDeReportes::class)
        ->and($d->hoy->format('Y-m-d'))->toBe('2026-10-17')
        ->and(array_keys($d->acciones))->toBe([$a['accion']])
        ->and($d->acciones[$a['accion']]['estado_accion'])->toBe('Finalizada')
        ->and($d->acciones[$a['accion']]['participante_ids'])->toEqualCanonicalizing([10, 11, 12])
        ->and($d->actividades)->toHaveCount(1)
        ->and($d->asistencias)->toHaveCount(3)
        ->and($d->completaciones)->toHaveCount(1)
        ->and($d->encuestas)->toBe([['actividad_id' => $a['actividad'], 'colaborador_id' => 10, 'calificacion' => 5]])
        ->and($d->beneficios)->toBe([$a['accion'] => 1000.0])
        ->and($d->evaluaciones)->toHaveCount(1)
        ->and($d->departamentoDe)->toBe([10 => 1, 11 => 1, 12 => null])
        ->and($d->noDisponibles)->toBe([]);
});

it('aísla por tenant', function () {
    $a = actividadConAsistencia([10 => 'Presente']);
    encuestaDe($a['actividad'], 10, 5);

    $d = ($this->recolectar)(2);

    expect($d->acciones)->toBe([])->and($d->actividades)->toBe([])->and($d->encuestas)->toBe([]);
});

it('el filtro por departamento deja solo a los participantes de ese departamento actual', function () {
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente']);
    $calculador = app(CalculadorDeIndicadores::class);

    $filas = $calculador->filas(($this->recolectar)(), ($this->filtro)(departamento: 1));

    expect($filas)->toHaveCount(1)->and($filas[0]['presentes'])->toBe([10, 11])->and($filas[0]['asistentes'])->toBe(2);
});

it('Microlearning excluye las actividades y el período usa la fecha de fin', function () {
    actividadConAsistencia([10 => 'Presente']);
    [, $accion2025] = accionEnPlan(['fecha_inicio' => '2025-03-02', 'fecha_fin' => '2025-03-13', 'colaborador_ids' => [10]], 'Ejecutando', ['anio' => 2025]);
    registroDeAsistencia(actividadDe($accion2025)['id'], 10, 'Presente');
    $calculador = app(CalculadorDeIndicadores::class);
    $d = ($this->recolectar)();

    expect($calculador->filas($d, ($this->filtro)(tipo: 'Microlearning')))->toBe([])
        ->and($calculador->filas($d, ($this->filtro)()))->toHaveCount(1);
});

it('una fuente caída se registra en noDisponibles en lugar de lanzar el error', function () {
    actividadConAsistencia([10 => 'Presente']);
    app()->instance(FuenteDePlanesInterface::class, new class implements FuenteDePlanesInterface
    {
        public function acciones(int $tenantId, DateTimeImmutable $hoy): array
        {
            throw new RuntimeException('Plan no disponible');
        }

        public function planes(int $tenantId): array
        {
            return [];
        }
    });

    $d = ($this->recolectar)();

    expect($d->noDisponibles)->toBe(['plan'])->and($d->acciones)->toBe([])->and($d->actividades)->toHaveCount(1)
        ->and($d->disponible('plan'))->toBeFalse()->and($d->disponible('actividades', 'dnf'))->toBeTrue();
});

it('una fuente de departamentos caída no impide recolectar el resto', function () {
    actividadConAsistencia([10 => 'Presente']);
    departamentosActualesCaidos();

    $d = ($this->recolectar)();

    expect($d->noDisponibles)->toBe(['personal'])->and($d->departamentoDe)->toBe([])->and($d->asistencias)->toHaveCount(1);
});
