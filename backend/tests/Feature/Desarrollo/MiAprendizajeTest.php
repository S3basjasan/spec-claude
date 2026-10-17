<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('asigna los publicados vinculados a una brecha activa y los iniciados sin completar', function () {
    $conBrecha = CompetenciaModel::factory()->create();
    $porBrecha = microDe(['titulo' => 'Por brecha', 'estado' => 'Publicado'], [$conBrecha->id]);
    $iniciado = microDe(['titulo' => 'Iniciado', 'estado' => 'Publicado']);
    $sinRelacion = microDe(['titulo' => 'Sin relación', 'estado' => 'Publicado']);
    $borrador = microDe(['titulo' => 'Borrador'], [$conBrecha->id]);
    [$usuario, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($iniciado->id, $ficha, 'EnProgreso');
    conBrechas([['competencia_id' => $conBrecha->id, 'nivel_requerido' => 'B']]);

    $datos = $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertOk()->json('data');
    $porTitulo = collect($datos['asignados'])->keyBy('titulo');

    expect($porTitulo->keys()->sort()->values()->all())->toBe(['Iniciado', 'Por brecha'])
        ->and($porTitulo['Por brecha']['motivo'])->toBe('brecha')
        ->and($porTitulo['Iniciado']['motivo'])->toBe('iniciado')
        ->and($porTitulo['Iniciado']['progreso']['estado'])->toBe('EnProgreso')
        ->and($datos['completados'])->toBe([])
        ->and($datos['resumen'])->toEqual(['completados' => 0, 'en_progreso' => 1, 'horas_acumuladas' => 0.0, 'certificados' => 0])
        ->and($sinRelacion->id)->not->toBe($borrador->id);
});

it('un contenido completado pasa a Completado y deja de estar asignado, aunque tenga brecha', function () {
    $competencia = CompetenciaModel::factory()->create();
    $micro = microDe(['titulo' => 'Hecho', 'estado' => 'Publicado', 'duracion_minutos' => 20], [$competencia->id]);
    $otro = microDe(['titulo' => 'Otro', 'estado' => 'Publicado', 'duracion_minutos' => 40], [$competencia->id]);
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($micro->id, $ficha, 'Completado');
    progresoMicro($otro->id, $ficha, 'Completado');
    conBrechas([['competencia_id' => $competencia->id, 'nivel_requerido' => 'A']]);

    $datos = $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertOk()->json('data');

    expect($datos['asignados'])->toBe([])
        ->and(array_column($datos['completados'], 'titulo'))->toContain('Hecho')->toContain('Otro')
        ->and($datos['resumen'])->toEqual(['completados' => 2, 'en_progreso' => 0, 'horas_acumuladas' => 1.0, 'certificados' => 2]);
});

it('incluye los archivados que ya completó, con su último puntaje, y no los archivados en progreso', function () {
    $quiz = quizDe(2, ['estado' => 'Archivado', 'titulo' => 'Quiz archivado']);
    $enProgreso = microDe(['estado' => 'Archivado', 'titulo' => 'Archivado en progreso']);
    [, $ficha] = colaboradorConCuentaDePlan();
    $progreso = progresoMicro($quiz->id, $ficha, 'Completado');
    intentoMicro($progreso->id, 'Finalizado', 2, 1);
    progresoMicro($enProgreso->id, $ficha, 'EnProgreso');
    conBrechas([]);

    $datos = $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertOk()->json('data');

    expect(array_column($datos['completados'], 'titulo'))->toBe(['Quiz archivado'])
        ->and($datos['completados'][0]['ultimo_puntaje'])->toBe(['correctas' => 1, 'total' => 2])
        ->and($datos['completados'][0]['completado_en'])->not->toBeNull()
        ->and($datos['asignados'])->toBe([]);
});

it('sin brechas ni contenidos devuelve listas vacías y no un error', function () {
    colaboradorConCuentaDePlan();
    conBrechas([]);

    $datos = $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertOk()->json('data');

    expect($datos['asignados'])->toBe([])->and($datos['completados'])->toBe([])
        ->and($datos['resumen']['completados'])->toBe(0)->and($datos)->not->toHaveKey('avisos');
});

it('si falla la lectura de brechas conserva los iniciados y avisa', function () {
    $iniciado = microDe(['estado' => 'Publicado', 'titulo' => 'Iniciado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($iniciado->id, $ficha, 'EnProgreso');
    conBrechas([], true);

    $datos = $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertOk()->json('data');

    expect(array_column($datos['asignados'], 'titulo'))->toBe(['Iniciado'])->and($datos['avisos'])->toBe(['brechas_no_disponibles']);
});

it('solo muestra los datos del colaborador autenticado', function () {
    $micro = microDe(['estado' => 'Publicado']);
    progresoMicro($micro->id, 99999, 'Completado');
    colaboradorConCuentaDePlan();
    conBrechas([]);

    $datos = $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertOk()->json('data');

    expect($datos['completados'])->toBe([])->and($datos['resumen']['completados'])->toBe(0);
});

it('el administrador y un usuario sin ficha reciben 403; sin sesión, 401', function () {
    $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertUnauthorized();

    actuarComoAdmin();
    $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertForbidden();

    actuarComoUsuario();
    $this->getJson('/api/microcontenidos/mi-aprendizaje')->assertForbidden();
});
