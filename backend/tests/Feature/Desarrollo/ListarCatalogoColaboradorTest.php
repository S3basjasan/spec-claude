<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('exige sesión para listar el catálogo', function () {
    $this->getJson('/api/catalogo-formativo')->assertUnauthorized();
});

it('al colaborador solo le entrega competencias activas aunque pida otro estado', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Activa uno']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Inactiva uno']);
    actuarComoColaborador();

    $nombres = fn ($r) => array_column($r->json('data'), 'nombre');

    expect($nombres($this->getJson('/api/catalogo-formativo')))->toBe(['Activa uno'])
        ->and($nombres($this->getJson('/api/catalogo-formativo?estado=Todos')))->toBe(['Activa uno'])
        ->and($nombres($this->getJson('/api/catalogo-formativo?estado=Inactivo')))->toBe(['Activa uno']);
});

it('cuenta solo recursos disponibles y marca la competencia como cubierta', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Comunicación']);
    $area = AreaFormativaModel::factory()->create(['nombre' => 'Habilidades']);
    $disponible = CursoModel::factory()->create();
    $archivado = CursoModel::factory()->archivado()->create();
    $micro = MicrocontenidoModel::factory()->create();
    configurarFormativa($competencia->id, $area->id, [$disponible->id, $archivado->id], [$micro->id]);
    actuarComoColaborador();

    $fila = $this->getJson('/api/catalogo-formativo')->assertOk()->json('data.0');

    expect($fila['cursos_count'])->toBe(1)
        ->and($fila['microcontenidos_count'])->toBe(1)
        ->and($fila['cubierta'])->toBeTrue()
        ->and($fila['area_formativa'])->toBe(['id' => $area->id, 'nombre' => 'Habilidades']);
});

it('una competencia sin área devuelve area_formativa null y sin cobertura', function () {
    CompetenciaModel::factory()->create();
    actuarComoColaborador();

    $fila = $this->getJson('/api/catalogo-formativo')->json('data.0');

    expect($fila['area_formativa'])->toBeNull()->and($fila['cubierta'])->toBeFalse()->and($fila['cursos_count'])->toBe(0);
});

it('incluye el nivel actual del colaborador y consulta el puerto con su propio id', function () {
    $a = CompetenciaModel::factory()->create(['nombre' => 'A competencia']);
    CompetenciaModel::factory()->create(['nombre' => 'B competencia']);
    $falso = conNiveles([$a->id => 'B']);
    $usuario = actuarComoColaborador();

    $datos = $this->getJson('/api/catalogo-formativo?competencia='.$a->id.'&page=1')->json('data');
    $todos = $this->getJson('/api/catalogo-formativo')->json('data');

    expect($datos[0]['nivel_actual'])->toBe('B')
        ->and($todos[1]['nivel_actual'])->toBeNull()
        ->and(array_unique($falso->consultados))->toBe([$usuario->id]);
});

it('si el puerto de niveles falla, el listado responde con nivel null', function () {
    CompetenciaModel::factory()->create();
    conNiveles([], true);
    actuarComoColaborador();

    $this->getJson('/api/catalogo-formativo')->assertOk()->assertJsonPath('data.0.nivel_actual', null);
});

it('ordena por nombre y pagina', function () {
    foreach (['Zeta', 'Alfa', 'Beta'] as $nombre) {
        CompetenciaModel::factory()->create(['nombre' => $nombre]);
    }
    actuarComoColaborador();

    $respuesta = $this->getJson('/api/catalogo-formativo?per_page=2&page=2');

    expect(array_column($respuesta->json('data'), 'nombre'))->toBe(['Zeta'])
        ->and($respuesta->json('meta.total'))->toBe(3)
        ->and($respuesta->json('meta.last_page'))->toBe(2);
});
