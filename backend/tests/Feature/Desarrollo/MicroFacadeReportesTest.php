<?php

use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

it('el resumen cuenta completados, en progreso, horas y certificados del colaborador', function () {
    $a = microDe(['duracion_minutos' => 20, 'estado' => 'Publicado']);
    $b = microDe(['duracion_minutos' => 40, 'estado' => 'Publicado']);
    $c = microDe(['duracion_minutos' => 15, 'estado' => 'Publicado']);
    progresoMicro($a->id, 7, 'Completado');
    progresoMicro($b->id, 7, 'Completado');
    progresoMicro($c->id, 7, 'EnProgreso');
    progresoMicro($a->id, 8, 'Completado');

    expect(DesarrolloFacade::getResumenAprendizaje(1, 7))->toBe(['completados' => 2, 'en_progreso' => 1, 'horas_acumuladas' => 1.0, 'certificados' => 2])
        ->and(DesarrolloFacade::getResumenAprendizaje(1, 999))->toBe(['completados' => 0, 'en_progreso' => 0, 'horas_acumuladas' => 0.0, 'certificados' => 0]);
});

it('las completaciones respetan el tenant y los filtros', function () {
    $micro = microDe(['titulo' => 'Uno', 'estado' => 'Publicado']);
    $otro = microDe(['titulo' => 'Dos', 'estado' => 'Publicado']);
    progresoMicro($micro->id, 7, 'Completado', 1);
    progresoMicro($otro->id, 7, 'Completado', 1);
    progresoMicro($micro->id, 8, 'Completado', 1);
    progresoMicro($micro->id, 9, 'Completado', 2);
    progresoMicro($otro->id, 10, 'EnProgreso', 1);

    $todas = DesarrolloFacade::getCompletaciones(1);

    expect($todas)->toHaveCount(3)
        ->and(array_keys($todas[0]))->toBe(['microcontenido_id', 'codigo', 'titulo', 'tipo', 'duracion_minutos', 'colaborador_id', 'fecha_inicio', 'fecha_completado'])
        ->and(DesarrolloFacade::getCompletaciones(1, ['colaborador_id' => 7]))->toHaveCount(2)
        ->and(DesarrolloFacade::getCompletaciones(1, ['microcontenido_id' => $micro->id]))->toHaveCount(2)
        ->and(DesarrolloFacade::getCompletaciones(2))->toHaveCount(1)
        ->and(DesarrolloFacade::getCompletaciones(1, ['desde' => now()->addDay()->toDateString()]))->toBe([])
        ->and(DesarrolloFacade::getCompletaciones(1, ['hasta' => now()->toDateString(), 'desde' => now()->toDateString()]))->toHaveCount(3);
});
