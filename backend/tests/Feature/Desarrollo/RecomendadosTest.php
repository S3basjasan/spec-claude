<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

function competenciaConCurso(string $nombre, bool $archivado = false, array $atributos = []): CompetenciaModel
{
    $competencia = CompetenciaModel::factory()->create(['nombre' => $nombre] + $atributos);
    $curso = $archivado ? CursoModel::factory()->archivado()->create() : CursoModel::factory()->create();
    configurarFormativa($competencia->id, null, [$curso->id]);

    return $competencia;
}

it('solo recomienda competencias activas con brecha y con recursos disponibles', function () {
    $conOferta = competenciaConCurso('Con oferta');
    $soloArchivado = competenciaConCurso('Solo archivado', true);
    $inactiva = competenciaConCurso('Inactiva', false, ['estado' => 'Inactivo']);
    $sinRecursos = CompetenciaModel::factory()->create(['nombre' => 'Sin recursos']);
    conBrechas(array_map(fn ($c) => ['competencia_id' => $c->id, 'nivel_requerido' => 'A'], [$conOferta, $soloArchivado, $inactiva, $sinRecursos]));
    conNiveles([$conOferta->id => 'C']);
    actuarComoUsuario();

    $datos = $this->getJson('/api/catalogo-formativo/recomendados')->assertOk()->json('data');

    expect($datos)->toHaveCount(1)
        ->and($datos[0]['competencia_id'])->toBe($conOferta->id)
        ->and($datos[0]['nivel_actual'])->toBe('C')
        ->and($datos[0]['nivel_requerido'])->toBe('A')
        ->and($datos[0]['cursos_count'])->toBe(1);
});

it('sin brechas devuelve una lista vacía', function () {
    competenciaConCurso('Con oferta');
    conBrechas([]);
    actuarComoUsuario();

    $this->getJson('/api/catalogo-formativo/recomendados')->assertOk()->assertJsonPath('data', []);
});

it('con el adaptador provisional sin datos devuelve una lista vacía', function () {
    competenciaConCurso('Con oferta');
    actuarComoUsuario();

    $this->getJson('/api/catalogo-formativo/recomendados')->assertOk()->assertJsonPath('data', []);
});

it('si el puerto de la DNF falla, responde 200 con lista vacía', function () {
    competenciaConCurso('Con oferta');
    conBrechas([], true);
    actuarComoUsuario();

    $this->getJson('/api/catalogo-formativo/recomendados')->assertOk()->assertJsonPath('data', []);
});

it('consulta el puerto solo con el id del usuario autenticado', function () {
    $competencia = competenciaConCurso('Con oferta');
    $falso = conBrechas([['competencia_id' => $competencia->id, 'nivel_requerido' => 'B']]);
    $usuario = actuarComoColaborador();

    $this->getJson('/api/catalogo-formativo/recomendados?usuario=999')->assertOk();

    expect(array_unique($falso->consultados))->toBe([$usuario->id]);
});

it('el administrador recibe una lista vacía y sin sesión 401', function () {
    $this->getJson('/api/catalogo-formativo/recomendados')->assertUnauthorized();

    $competencia = competenciaConCurso('Con oferta');
    conBrechas([['competencia_id' => $competencia->id, 'nivel_requerido' => 'B']]);
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo/recomendados')->assertOk()->assertJsonPath('data', []);
});
