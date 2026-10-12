<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroComportamientosModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('crea el registro como Administrador con versión 1 y comportamientos agrupados por grado', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);

    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id))
        ->assertCreated()
        ->assertJsonPath('data.competencia_id', $competencia->id)
        ->assertJsonPath('data.nombre', 'Liderazgo')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.comportamientos.A', ['Lidera con el ejemplo.', 'Inspira a otros.'])
        ->assertJsonPath('data.comportamientos.D', ['Sigue las indicaciones.']);

    expect(ComportamientoModel::query()->count())->toBe(6);
});

it('registra quién lo creó', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);
    $competencia = CompetenciaModel::factory()->create();

    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id))->assertCreated();

    $registro = RegistroComportamientosModel::query()->first();
    expect($registro->created_by)->toBe($admin->id)->and($registro->updated_by)->toBeNull();
});

it('rechaza con 401 sin sesión y con 403 al Usuario', function () {
    $competencia = CompetenciaModel::factory()->create();
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id))->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id))
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);
    expect(RegistroComportamientosModel::query()->count())->toBe(0);
});

it('devuelve 422 sin competencia o con una inexistente, inactiva o que ya tiene registro', function () {
    actuarComoAdmin();
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    $conRegistro = registroDe();

    $this->postJson('/api/comportamientos', datosComportamientos(0, ['competencia_id' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $this->postJson('/api/comportamientos', datosComportamientos(999))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $this->postJson('/api/comportamientos', datosComportamientos($inactiva->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $this->postJson('/api/comportamientos', datosComportamientos($conRegistro->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);

    expect(RegistroComportamientosModel::query()->count())->toBe(1);
});

it('exige los cuatro grados y rechaza claves extra', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();
    $datos = datosComportamientos($competencia->id);
    unset($datos['comportamientos']['B']);
    $datos['comportamientos']['E'] = ['Extra'];

    $this->postJson('/api/comportamientos', $datos)->assertUnprocessable();
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => []]))
        ->assertUnprocessable();
    expect(RegistroComportamientosModel::query()->count())->toBe(0);
});

it('acepta de 1 a 4 comportamientos por grado y rechaza 0 o 5', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();
    $base = datosComportamientos($competencia->id)['comportamientos'];

    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => [...$base, 'A' => []]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['comportamientos.A']);
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => [...$base, 'C' => ['1', '2', '3', '4', '5']]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['comportamientos.C']);
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => [...$base, 'C' => ['1', '2', '3', '4']]]))
        ->assertCreated();
});

it('rechaza textos vacíos, de más de 300 caracteres o repetidos en el mismo grado', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();
    $base = datosComportamientos($competencia->id)['comportamientos'];

    $respuesta = $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => [
        ...$base,
        'A' => ['   ', str_repeat('x', 301)],
        'B' => ['Dirige', 'dirige '],
    ]]))->assertUnprocessable();

    $respuesta->assertJsonValidationErrors(['comportamientos.A.0', 'comportamientos.A.1', 'comportamientos.B.1']);
    expect(RegistroComportamientosModel::query()->count())->toBe(0);
});

it('acepta exactamente 300 caracteres y el mismo texto en grados distintos', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();
    $base = datosComportamientos($competencia->id)['comportamientos'];

    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => [
        ...$base,
        'A' => [str_repeat('x', 300)],
        'B' => ['Texto igual'],
        'C' => ['Texto igual'],
    ]]))->assertCreated();
});

it('recorta los espacios de cada texto al guardar', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();
    $base = datosComportamientos($competencia->id)['comportamientos'];

    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id, ['comportamientos' => [...$base, 'D' => ['  Con espacios  ']]]))
        ->assertCreated()
        ->assertJsonPath('data.comportamientos.D', ['Con espacios']);
});
