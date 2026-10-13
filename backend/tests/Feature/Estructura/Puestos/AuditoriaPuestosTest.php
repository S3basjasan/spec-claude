<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * Filas del historial de un puesto, en el orden en que se escribieron: [campo, anterior, nuevo, usuario].
 *
 * @return array<int, array{0: string, 1: ?string, 2: ?string, 3: ?int}>
 */
function historialDe(int $puestoId, int $desdeId = 0): array
{
    return AuditoriaCambioModel::query()
        ->where('entidad', 'puesto')
        ->where('entidad_id', $puestoId)
        ->where('id', '>', $desdeId)
        ->orderBy('id')
        ->get()
        ->map(fn (AuditoriaCambioModel $fila) => [$fila->campo, $fila->valor_anterior, $fila->valor_nuevo, $fila->user_id])
        ->all();
}

function ultimaFilaDelHistorial(): int
{
    return (int) AuditoriaCambioModel::query()->max('id');
}

function actuarComo(UserModel $usuario): void
{
    Sanctum::actingAs($usuario, ['admin']);
}

beforeEach(function () {
    $this->ana = UserModel::factory()->admin()->create();
    $this->beto = UserModel::factory()->admin()->create();
    $this->liderazgo = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);
    $this->equipo = CompetenciaModel::factory()->create(['nombre' => 'Trabajo en equipo']);
});

it('la creación registra quién lo creó y una fila por cada dato, función, requisito y competencia con anterior vacío', function () {
    actuarComo($this->ana);

    $respuesta = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->assertCreated();
    $id = $respuesta->json('data.id');
    $codigo = $respuesta->json('data.codigo');

    $filas = historialDe($id);
    expect($filas)->toHaveCount(10)
        ->and(array_column($filas, 3))->each->toBe($this->ana->id)
        ->and(array_column($filas, 1))->each->toBeNull()
        ->and(array_slice($filas, 0, 5))->toBe([
            ['codigo', null, $codigo, $this->ana->id],
            ['nombre', null, 'Analista de datos', $this->ana->id],
            ['descripcion', null, 'Convierte datos en reportes para decidir mejor.', $this->ana->id],
            ['mision', null, 'Transformar los datos de la organización en información confiable.', $this->ana->id],
            ['estado', null, 'Activo', $this->ana->id],
        ])
        ->and(array_slice($filas, 5))->toBe([
            ['funciones', null, 'Esencial: Recopilar y consolidar datos.', $this->ana->id],
            ['funciones', null, 'Complementaria: Documentar indicadores.', $this->ana->id],
            ['requisitos', null, 'Formación: Título de tercer nivel afín.', $this->ana->id],
            ['requisitos', null, 'Experiencia: 2 años en análisis de datos.', $this->ana->id],
            ['competencias', null, "{$this->liderazgo->fresh()->codigo} Liderazgo: grado B", $this->ana->id],
        ]);
    expect(AuditoriaCambioModel::query()->whereNull('created_at')->count())->toBe(0);
});

it('registra quién modificó por última vez sin alterar quién lo creó, y una edición sin cambios no lo toca', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->assertCreated()->json('data.id');
    $puesto = PuestoModel::query()->findOrFail($id);
    expect($puesto->created_by)->toBe($this->ana->id)->and($puesto->updated_by)->toBeNull();

    actuarComo($this->beto);
    $this->putJson("/api/puestos/{$id}", cuerpoDelPuesto($puesto, ['mision' => 'Nueva misión.']))->assertOk();
    $puesto->refresh();
    expect($puesto->created_by)->toBe($this->ana->id)
        ->and($puesto->updated_by)->toBe($this->beto->id)
        ->and($puesto->version)->toBe(2);

    actuarComo($this->ana);
    $this->putJson("/api/puestos/{$id}", cuerpoDelPuesto($puesto))->assertOk();
    $puesto->refresh();
    expect($puesto->updated_by)->toBe($this->beto->id)->and($puesto->version)->toBe(2);
});

it('cambiar el nombre escribe una fila con el valor anterior, el nuevo, el usuario y la fecha', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->json('data.id');
    $desde = ultimaFilaDelHistorial();

    actuarComo($this->beto);
    $this->putJson("/api/puestos/{$id}", cuerpoDelPuesto(PuestoModel::query()->findOrFail($id), ['nombre' => 'Analista senior']))->assertOk();

    expect(historialDe($id, $desde))->toBe([['nombre', 'Analista de datos', 'Analista senior', $this->beto->id]]);
    expect(AuditoriaCambioModel::query()->where('id', '>', $desde)->first()->created_at)->not->toBeNull();
});

it('agregar una función, quitar un requisito y cambiar el grado de una competencia escriben una fila por elemento', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id, ['requisitos' => [
        ['tipo' => 'Formacion', 'descripcion' => 'Título de tercer nivel afín.'],
        ['tipo' => 'Experiencia', 'descripcion' => '2 años en análisis de datos.'],
        ['tipo' => 'Capacitacion', 'descripcion' => 'Curso de SQL.'],
    ]]))->json('data.id');
    $desde = ultimaFilaDelHistorial();
    $puesto = PuestoModel::query()->findOrFail($id);
    $cuerpo = cuerpoDelPuesto($puesto);

    actuarComo($this->beto);
    $this->putJson("/api/puestos/{$id}", array_replace($cuerpo, [
        'funciones' => [...$cuerpo['funciones'], ['descripcion' => 'Capacitar al equipo.', 'tipo' => 'Complementaria']],
        'requisitos' => array_slice($cuerpo['requisitos'], 0, 2),
        'competencias' => [['competencia_id' => $this->liderazgo->id, 'grado_requerido' => 'A']],
    ]))->assertOk();

    $codigo = $this->liderazgo->fresh()->codigo;
    expect(historialDe($id, $desde))->toBe([
        ['funciones', null, 'Complementaria: Capacitar al equipo.', $this->beto->id],
        ['requisitos', 'Capacitación: Curso de SQL.', null, $this->beto->id],
        ['competencias', "$codigo Liderazgo: grado B", "$codigo Liderazgo: grado A", $this->beto->id],
    ]);
});

it('agregar y quitar competencias escribe altas y bajas con su código y nombre', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->json('data.id');
    $desde = ultimaFilaDelHistorial();

    $this->putJson("/api/puestos/{$id}", cuerpoDelPuesto(PuestoModel::query()->findOrFail($id), [
        'competencias' => [['competencia_id' => $this->equipo->id, 'grado_requerido' => 'C']],
    ]))->assertOk();

    expect(historialDe($id, $desde))->toBe([
        ['competencias', "{$this->liderazgo->fresh()->codigo} Liderazgo: grado B", null, $this->ana->id],
        ['competencias', null, "{$this->equipo->fresh()->codigo} Trabajo en equipo: grado C", $this->ana->id],
    ]);
});

it('cambiar el texto de una función escribe una baja y un alta; cambiar solo su tipo, una modificación', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->json('data.id');
    $cuerpo = cuerpoDelPuesto(PuestoModel::query()->findOrFail($id));

    $desde = ultimaFilaDelHistorial();
    $this->putJson("/api/puestos/{$id}", array_replace($cuerpo, [
        'funciones' => [['descripcion' => 'Consolidar datos de varias fuentes.', 'tipo' => 'Esencial'], $cuerpo['funciones'][1]],
    ]))->assertOk();
    expect(historialDe($id, $desde))->toBe([
        ['funciones', 'Esencial: Recopilar y consolidar datos.', null, $this->ana->id],
        ['funciones', null, 'Esencial: Consolidar datos de varias fuentes.', $this->ana->id],
    ]);

    $cuerpo = cuerpoDelPuesto(PuestoModel::query()->findOrFail($id));
    $desde = ultimaFilaDelHistorial();
    $this->putJson("/api/puestos/{$id}", array_replace($cuerpo, [
        'funciones' => [$cuerpo['funciones'][0], ['descripcion' => $cuerpo['funciones'][1]['descripcion'], 'tipo' => 'Esencial']],
    ]))->assertOk();
    expect(historialDe($id, $desde))->toBe([
        ['funciones', 'Complementaria: Documentar indicadores.', 'Esencial: Documentar indicadores.', $this->ana->id],
    ]);
});

it('reordenar las funciones no escribe filas en el historial pero sí actualiza la última modificación', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->json('data.id');
    $puesto = PuestoModel::query()->findOrFail($id);
    $cuerpo = cuerpoDelPuesto($puesto);
    $desde = ultimaFilaDelHistorial();

    actuarComo($this->beto);
    $this->putJson("/api/puestos/{$id}", array_replace($cuerpo, ['funciones' => array_reverse($cuerpo['funciones'])]))->assertOk();

    expect(historialDe($id, $desde))->toBe([])
        ->and($puesto->fresh()->updated_by)->toBe($this->beto->id)
        ->and($puesto->fresh()->version)->toBe(2);
});

it('quitar una función en medio solo registra la baja, no la renumeración de las demás', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id, ['funciones' => [
        ['descripcion' => 'Uno', 'tipo' => 'Esencial'],
        ['descripcion' => 'Dos', 'tipo' => 'Complementaria'],
        ['descripcion' => 'Tres', 'tipo' => 'Complementaria'],
    ]]))->json('data.id');
    $cuerpo = cuerpoDelPuesto(PuestoModel::query()->findOrFail($id));
    $desde = ultimaFilaDelHistorial();

    $this->putJson("/api/puestos/{$id}", array_replace($cuerpo, ['funciones' => [$cuerpo['funciones'][0], $cuerpo['funciones'][2]]]))->assertOk();

    expect(historialDe($id, $desde))->toBe([['funciones', 'Complementaria: Dos', null, $this->ana->id]]);
});

it('guardar sin cambios no escribe filas', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->json('data.id');
    $desde = ultimaFilaDelHistorial();

    $this->putJson("/api/puestos/{$id}", cuerpoDelPuesto(PuestoModel::query()->findOrFail($id)))->assertOk();

    expect(historialDe($id, $desde))->toBe([]);
});

it('desactivar y activar escriben cada uno una fila de estado y conservan las anteriores', function () {
    actuarComo($this->ana);
    $id = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id))->json('data.id');
    $desde = ultimaFilaDelHistorial();
    $totalAntes = count(historialDe($id));

    actuarComo($this->beto);
    $this->patchJson("/api/puestos/{$id}/estado", ['estado' => 'Inactivo'])->assertOk();
    actuarComo($this->ana);
    $this->patchJson("/api/puestos/{$id}/estado", ['estado' => 'Activo'])->assertOk();

    expect(historialDe($id, $desde))->toBe([
        ['estado', 'Activo', 'Inactivo', $this->beto->id],
        ['estado', 'Inactivo', 'Activo', $this->ana->id],
    ])->and(count(historialDe($id)))->toBe($totalAntes + 2);
});

it('todas las filas llevan la entidad, el id del puesto y la fecha, y no se mezclan entre puestos', function () {
    actuarComo($this->ana);
    $primero = $this->postJson('/api/puestos', datosPuesto($this->liderazgo->id, ['nombre' => 'Primero']))->json('data.id');
    $segundo = $this->postJson('/api/puestos', datosPuesto($this->equipo->id, ['nombre' => 'Segundo']))->json('data.id');

    expect(AuditoriaCambioModel::query()->where('entidad', '<>', 'puesto')->count())->toBe(0)
        ->and(AuditoriaCambioModel::query()->whereNull('created_at')->count())->toBe(0)
        ->and(historialDe($primero))->toHaveCount(10)
        ->and(historialDe($segundo))->toHaveCount(10)
        ->and(array_column(historialDe($segundo), 2))->toContain('Segundo')
        ->and(array_column(historialDe($primero), 2))->not->toContain('Segundo');
});
