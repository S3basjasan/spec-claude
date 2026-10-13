<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('actualiza con cambios: sube la versión, registra al editor y conserva código, autor y estado', function () {
    $autor = UserModel::factory()->admin()->create();
    $puesto = puestoDe(['nombre' => 'Analista', 'created_by' => $autor->id]);
    $editor = UserModel::factory()->admin()->create();
    Sanctum::actingAs($editor, ['admin']);
    $codigo = $puesto->fresh()->codigo;

    $respuesta = $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, [
        'nombre' => 'Analista senior',
        'funciones' => [
            ['descripcion' => 'Función nueva', 'tipo' => 'Esencial'],
            ['descripcion' => 'Otra función', 'tipo' => 'Complementaria'],
            ['descripcion' => 'Tercera', 'tipo' => 'Complementaria'],
        ],
    ]))->assertOk();

    expect($respuesta->json('data.nombre'))->toBe('Analista senior')
        ->and($respuesta->json('data.version'))->toBe(2)
        ->and($respuesta->json('data.codigo'))->toBe($codigo)
        ->and($respuesta->json('data.estado'))->toBe('Activo')
        ->and(array_column($respuesta->json('data.funciones'), 'orden'))->toBe([1, 2, 3])
        ->and(array_column($respuesta->json('data.funciones'), 'descripcion'))->toBe(['Función nueva', 'Otra función', 'Tercera']);

    $recargado = $puesto->fresh();
    expect($recargado->created_by)->toBe($autor->id)
        ->and($recargado->updated_by)->toBe($editor->id)
        ->and($recargado->nombre_clave)->toBe('analista senior')
        ->and($recargado->nombre_busqueda)->toBe('analista senior');
});

it('permite editar un puesto inactivo y sigue inactivo', function () {
    $puesto = puestoDe(['nombre' => 'Viejo', 'estado' => 'Inactivo']);
    actuarComoAdmin();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['mision' => 'Nueva misión.']))
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.mision', 'Nueva misión.');
});

it('guardar sin cambios reales no modifica nada, ni con espacios alrededor', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    DB::table('puestos')->where('id', $puesto->id)->update(['updated_at' => '2020-01-01 00:00:00']);
    actuarComoAdmin();
    $antes = AuditoriaCambioModel::query()->count();

    $respuesta = $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['nombre' => '  Analista  ', 'mision' => " {$puesto->mision} "]))
        ->assertOk();

    expect($respuesta->json('data.version'))->toBe(1);
    $recargado = DB::table('puestos')->where('id', $puesto->id)->first();
    expect($recargado->updated_at)->toBe('2020-01-01 00:00:00')
        ->and($recargado->updated_by)->toBeNull()
        ->and(AuditoriaCambioModel::query()->count())->toBe($antes);
});

it('un cambio que solo reordena las funciones es real: sube la versión y registra al editor', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    $editor = UserModel::factory()->admin()->create();
    Sanctum::actingAs($editor, ['admin']);
    $cuerpo = cuerpoDelPuesto($puesto);
    $original = array_column($cuerpo['funciones'], 'descripcion');

    $respuesta = $this->putJson("/api/puestos/{$puesto->id}", array_replace($cuerpo, ['funciones' => array_reverse($cuerpo['funciones'])]))
        ->assertOk();

    expect($respuesta->json('data.version'))->toBe(2)
        ->and(array_column($respuesta->json('data.funciones'), 'descripcion'))->toBe(array_reverse($original))
        ->and(array_column($respuesta->json('data.funciones'), 'orden'))->toBe([1, 2])
        ->and($puesto->fresh()->updated_by)->toBe($editor->id);
});

it('rechaza una versión desactualizada con 409 y exige la versión', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['mision' => 'Primera edición.']))->assertOk();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto->fresh(), ['mision' => 'Segunda edición.', 'version' => 1]))
        ->assertStatus(409)
        ->assertJsonPath('message', 'El puesto cambió desde que lo abriste. Recarga para ver la versión actual.');

    $sinVersion = cuerpoDelPuesto($puesto->fresh(), ['mision' => 'Tercera.']);
    unset($sinVersion['version']);
    $this->putJson("/api/puestos/{$puesto->id}", $sinVersion)->assertUnprocessable()->assertJsonValidationErrors('version');
    expect($puesto->fresh()->mision)->toBe('Primera edición.');
});

it('el código no se puede cambiar', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['codigo' => 'PU-999']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codigo' => 'El código de un puesto no se puede cambiar.']);
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['codigo' => $puesto->fresh()->codigo, 'mision' => 'Otra.']))
        ->assertOk();

    expect($puesto->fresh()->codigo)->toBe(sprintf('PU-%03d', $puesto->id));
});

it('exige sesión, deniega al Usuario e informa si el puesto no existe', function () {
    $puesto = puestoDe();
    $cuerpo = cuerpoDelPuesto($puesto);

    $this->putJson("/api/puestos/{$puesto->id}", $cuerpo)->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/puestos/{$puesto->id}", $cuerpo)->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/puestos/999999', $cuerpo)->assertNotFound()->assertJsonPath('message', 'El puesto no existe.');
});

it('conservar el propio nombre, aunque cambie la caja, no es un duplicado; el de otro puesto sí', function () {
    $puesto = puestoDe(['nombre' => 'Analista de datos']);
    puestoDe(['nombre' => 'Jefe de talento']);
    actuarComoAdmin();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['nombre' => 'ANALISTA DE DATOS']))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'ANALISTA DE DATOS');

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto->fresh(), ['nombre' => ' jefe de TALENTO ']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre' => 'Ya existe un puesto con ese nombre.']);

    expect($puesto->fresh()->nombre)->toBe('ANALISTA DE DATOS');
});

it('una competencia inactiva ya asignada se mantiene con su grado o se quita, pero no cambia de grado ni se vuelve a agregar', function () {
    $activa = CompetenciaModel::factory()->create(['nombre' => 'Activa']);
    $retirada = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    $puesto = puestoDe(['nombre' => 'Analista']);
    PuestoCompetenciaModel::query()->create(['puesto_id' => $puesto->id, 'competencia_id' => $retirada->id, 'grado_requerido' => 'C']);
    actuarComoAdmin();

    $mantenida = [
        ['competencia_id' => $activa->id, 'grado_requerido' => 'A'],
        ['competencia_id' => $retirada->id, 'grado_requerido' => 'C'],
    ];

    // Se mantiene igual: se acepta.
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['competencias' => $mantenida]))
        ->assertOk()
        ->assertJsonPath('data.competencias.1.estado', 'Inactivo');

    // Cambiarle el grado: se rechaza en su fila.
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto->fresh(), [
        'competencias' => [$mantenida[0], ['competencia_id' => $retirada->id, 'grado_requerido' => 'A']],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['competencias.1.grado_requerido']);

    // Quitarla: se acepta, y ya no se puede volver a agregar.
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto->fresh(), ['competencias' => [$mantenida[0]]]))->assertOk();
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto->fresh(), ['competencias' => $mantenida]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['competencias.1.competencia_id']);
});

it('no permite agregar una competencia inactiva nueva', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $competencias = cuerpoDelPuesto($puesto)['competencias'];
    $competencias[] = ['competencia_id' => $inactiva->id, 'grado_requerido' => 'A'];

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['competencias' => $competencias]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['competencias.1.competencia_id']);
});

it('aplica las mismas reglas de validación que al crear', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['funciones' => [['descripcion' => 'Solo complementaria', 'tipo' => 'Complementaria']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('funciones');
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['requisitos' => [['tipo' => 'Formacion', 'descripcion' => 'Título.']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('requisitos');
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['competencias' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('competencias');
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['nombre' => str_repeat('n', 51)]))
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');

    expect($puesto->fresh()->version)->toBe(1);
});

it('la edición no cambia el estado del puesto aunque lo envíen', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['estado' => 'Inactivo', 'mision' => 'Nueva.']))->assertOk();

    expect($puesto->fresh()->estado)->toBe('Activo');
});
