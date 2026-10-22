<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialParametrosAdecuacionModel;

function cuerpoParametros(array $cambios = []): array
{
    return $cambios + ['pesos' => ['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30], 'umbral_meses' => 12];
}

it('devuelve los valores iniciales 40/30/30 y 12 meses con el historial vacío', function () {
    actuarComoAdminDeTenant();

    $d = $this->getJson('/api/adecuaciones/parametros')->assertOk()->json('data');

    expect($d['pesos'])->toBe(['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30])
        ->and($d['umbral_meses'])->toBe(12)
        ->and($d['historial'])->toBe([])
        ->and($d['meta']['total'])->toBe(0);
});

it('guarda los cambios y registra una fila de historial por campo con usuario, fecha, valor anterior y nuevo', function () {
    $admin = actuarComoAdminDeTenant();

    $d = $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['pesos' => ['Competencia' => 50, 'Conocimiento' => 20, 'Experiencia' => 30], 'umbral_meses' => 6]))
        ->assertOk()->json('data');

    expect($d['pesos'])->toBe(['Competencia' => 50, 'Conocimiento' => 20, 'Experiencia' => 30])
        ->and($d['umbral_meses'])->toBe(6)
        ->and($d['historial'])->toHaveCount(3);
    $campos = collect($d['historial'])->keyBy('campo');
    expect($campos['umbral_meses'])->toMatchArray(['valor_anterior' => '12', 'valor_nuevo' => '6'])
        ->and($campos['peso_competencias'])->toMatchArray(['valor_anterior' => '40', 'valor_nuevo' => '50'])
        ->and($campos['peso_conocimientos'])->toMatchArray(['valor_anterior' => '30', 'valor_nuevo' => '20'])
        ->and($campos->has('peso_experiencia'))->toBeFalse()
        ->and($campos['umbral_meses']['usuario']['id'])->toBe($admin->id)
        ->and($campos['umbral_meses']['fecha'])->not->toBeEmpty();
    expect($this->getJson('/api/adecuaciones/parametros')->json('data.umbral_meses'))->toBe(6);
});

it('un cambio sin modificaciones no genera historial', function () {
    actuarComoAdminDeTenant();

    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros())->assertOk()->assertJsonPath('data.historial', []);
    expect(HistorialParametrosAdecuacionModel::query()->count())->toBe(0);
});

it('rechaza con 422 pesos que no suman 100, negativos o no enteros', function () {
    actuarComoAdminDeTenant();

    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['pesos' => ['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 20]]))
        ->assertStatus(422)->assertJsonPath('code', 'pesos_invalidos');
    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['pesos' => ['Competencia' => -10, 'Conocimiento' => 60, 'Experiencia' => 50]]))
        ->assertStatus(422)->assertJsonValidationErrors('pesos.Competencia');
    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['pesos' => ['Competencia' => 40.5, 'Conocimiento' => 29.5, 'Experiencia' => 30]]))
        ->assertStatus(422)->assertJsonValidationErrors('pesos.Competencia');
    $this->putJson('/api/adecuaciones/parametros', ['umbral_meses' => 12])->assertStatus(422)->assertJsonValidationErrors('pesos');
    expect(HistorialParametrosAdecuacionModel::query()->count())->toBe(0);
});

it('rechaza con 422 un umbral menor que 1 o no entero', function () {
    actuarComoAdminDeTenant();

    foreach ([0, -3, 2.5, 'seis'] as $umbral) {
        $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['umbral_meses' => $umbral]))->assertStatus(422)->assertJsonValidationErrors('umbral_meses');
    }
});

it('el nuevo umbral rige para las adecuaciones siguientes y no recalcula las guardadas', function () {
    [$ruta, $inscripcion] = escenarioDeAdecuacion(['niveles' => [10 => [1 => 'C', 2 => 'C', 3 => 'C']]]);
    $cuerpo = ['colaborador_id' => 10, 'inscripcion_id' => $inscripcion->id, 'tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 8]]];

    $antes = $this->postJson('/api/adecuaciones', $cuerpo)->assertCreated()->json('data');
    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['umbral_meses' => 6]))->assertOk();
    $despues = $this->postJson('/api/adecuaciones', $cuerpo)->assertCreated()->json('data');

    expect($antes)->toMatchArray(['preparacion' => 'EnDesarrollo', 'umbral_meses' => 12])
        ->and($despues)->toMatchArray(['preparacion' => 'NoLista', 'umbral_meses' => 6]);
    expect(AdecuacionModel::query()->find($antes['id'])->preparacion)->toBe('EnDesarrollo');
    unset($ruta);
});

it('pagina el historial', function () {
    actuarComoAdminDeTenant();
    foreach ([11, 10, 9, 8] as $umbral) {
        $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['umbral_meses' => $umbral]))->assertOk();
    }

    $d = $this->getJson('/api/adecuaciones/parametros?per_page=2&page=2')->assertOk()->json('data');

    expect($d['historial'])->toHaveCount(2)->and($d['meta'])->toBe(['page' => 2, 'per_page' => 2, 'total' => 4, 'last_page' => 2]);
});

it('los parámetros y el historial son por tenant', function () {
    actuarComoAdminDeTenant();
    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros(['umbral_meses' => 3]))->assertOk();

    actuarComoAdminDeTenant(2);

    $this->getJson('/api/adecuaciones/parametros')->assertOk()->assertJsonPath('data.umbral_meses', 12)->assertJsonPath('data.meta.total', 0);
});

it('exige la clave de parámetros y sesión', function () {
    $this->getJson('/api/adecuaciones/parametros')->assertUnauthorized();
    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros())->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/adecuaciones/parametros')->assertForbidden();
    $this->putJson('/api/adecuaciones/parametros', cuerpoParametros())->assertForbidden();
});
