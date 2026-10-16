<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;

it('parte en NoInformada y pasa a Disponible con observaciones sin crear otros vínculos (GP03-CA05)', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $antes = [FormacionAcademicaModel::query()->count(), ExperienciaAnteriorModel::query()->count(), ColaboradorModel::query()->count()];

    $respuesta = $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'Disponible', 'observaciones' => 'Interesada en traslado'])->assertOk();

    expect($respuesta->json('data.estado'))->toBe('Disponible')
        ->and($respuesta->json('data.version'))->toBe(2)
        ->and($respuesta->json('data.actualizada_at'))->not->toBeNull()
        ->and([FormacionAcademicaModel::query()->count(), ExperienciaAnteriorModel::query()->count(), ColaboradorModel::query()->count()])->toBe($antes)
        ->and($ficha->fresh()->movilidad_actualizada_por)->toBe($admin->id);
});

it('repetir el mismo valor no cambia fecha ni versión', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $primera = $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'Disponible', 'observaciones' => 'x'])->json('data');

    $segunda = $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 2, 'estado' => 'Disponible', 'observaciones' => 'x'])->assertOk()->json('data');

    expect($segunda['version'])->toBe(2)->and($segunda['actualizada_at'])->toBe($primera['actualizada_at']);
});

it('valida estado y observaciones dejando el bloque intacto', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $url = "/api/colaboradores/{$ficha->id}/movilidad";

    $this->patchJson($url, ['version' => 1, 'estado' => 'Quizá'])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->patchJson($url, ['version' => 1, 'estado' => 'Disponible', 'observaciones' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors(['observaciones']);
    $this->patchJson($url, ['version' => 1, 'estado' => 'Disponible', 'observaciones' => str_repeat('a', 2000)])->assertOk();

    $otra = fichaDe();
    $this->patchJson("/api/colaboradores/{$otra->id}/movilidad", ['version' => 1, 'estado' => 'Quizá'])->assertUnprocessable();
    expect($otra->fresh()->movilidad_estado)->toBe('NoInformada');
});

it('responde 409 con versión vieja y 404 de otro tenant', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $ajena = fichaDe([], 2);

    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'Disponible'])->assertOk();
    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'NoDisponible'])->assertStatus(409)->assertJsonPath('code', 'REGISTRO_MODIFICADO');
    $this->patchJson("/api/colaboradores/{$ajena->id}/movilidad", ['version' => 1, 'estado' => 'Disponible'])->assertNotFound();
});

it('movilidad y ficha no se bloquean entre sí', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'Disponible'])->assertOk();
    $this->patchJson("/api/colaboradores/{$ficha->id}", ['version' => 1, 'nombres' => 'Nuevo'])->assertOk()->assertJsonPath('data.version', 2);
    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 2, 'estado' => 'NoDisponible'])->assertOk();

    $fresca = $ficha->fresh();
    expect($fresca->version)->toBe(2)->and($fresca->movilidad_estado)->toBe('NoDisponible');
});

it('la movilidad no toca la versión ni el updated_at de la ficha', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['updated_at' => '2024-01-01 10:00:00']);

    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'Disponible'])->assertOk();

    $fresca = $ficha->fresh();
    expect($fresca->version)->toBe(1)->and($fresca->updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
});

it('NoInformada se distingue de NoDisponible y el colaborador no puede escribir', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 1, 'estado' => 'NoDisponible'])->assertOk();
    $otra = fichaDe();

    expect($this->getJson("/api/colaboradores/{$ficha->id}/desarrollo")->json('data.movilidad.estado'))->toBe('NoDisponible')
        ->and($this->getJson("/api/colaboradores/{$otra->id}/desarrollo")->json('data.movilidad.estado'))->toBe('NoInformada');

    actuarComoUsuarioDeTenant();
    $this->patchJson("/api/colaboradores/{$ficha->id}/movilidad", ['version' => 2, 'estado' => 'Disponible'])->assertForbidden();
});
