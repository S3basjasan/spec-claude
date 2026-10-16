<?php

use App\Modules\GestionPersonal\Domain\Exceptions\RegistroModificado;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;

/*
 * El proyecto no tiene un arnés multiproceso previo: estas pruebas reproducen la carrera de forma determinista
 * (la condición `WHERE version = ?` del repositorio es la que decide al ganador en la base de datos).
 */

it('dos correcciones con la misma versión: una gana y la otra recibe 409', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $formacion = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id]);
    $url = "/api/colaboradores/{$ficha->id}/formaciones/{$formacion->id}";

    $estados = [
        $this->patchJson($url, ['version' => 1, 'institucion' => 'A'])->status(),
        $this->patchJson($url, ['version' => 1, 'institucion' => 'B'])->status(),
    ];

    expect($estados)->toBe([200, 409])
        ->and($formacion->fresh()->institucion)->toBe('A')
        ->and($formacion->fresh()->version)->toBe(2);
});

it('el repositorio decide por la versión aunque el caso de uso haya leído un estado anterior', function () {
    $ficha = fichaDe();
    $formacion = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id]);
    $repo = app(DesarrolloRepositoryInterface::class);

    $repo->actualizarFormacion(1, $ficha->id, $formacion->id, 1, 1, ['institucion' => 'Primera']);

    expect(fn () => $repo->actualizarFormacion(1, $ficha->id, $formacion->id, 1, 1, ['institucion' => 'Segunda']))
        ->toThrow(RegistroModificado::class)
        ->and($formacion->fresh()->institucion)->toBe('Primera');
});

it('dos altas en la misma ficha se aceptan ambas', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $cuerpo = ['nivel' => 'X', 'titulo_programa' => 'X', 'institucion' => 'X', 'estado' => 'Completada'];

    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", $cuerpo)->assertCreated();
    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", $cuerpo)->assertCreated();

    expect(FormacionAcademicaModel::query()->count())->toBe(2);
});

it('movilidad y ficha se actualizan sin bloquearse aunque lleguen con la misma versión inicial', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'Disponible'])->assertOk();
    $this->patchJson("/api/colaboradores/{$ficha->id}", ['version' => 1, 'nombres' => 'Otro'])->assertOk();

    $fresca = $ficha->fresh();
    expect($fresca->movilidad_estado)->toBe('Disponible')->and($fresca->nombres)->toBe('Otro');
});
