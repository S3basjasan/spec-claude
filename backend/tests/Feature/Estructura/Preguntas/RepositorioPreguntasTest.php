<?php

use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTienePreguntas;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroPreguntasModel;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

function preguntasRepo(): PreguntaRepositoryInterface
{
    return app(PreguntaRepositoryInterface::class);
}

/** @return array<int, string> */
function cuatroTextos(string $prefijo = 'Pregunta'): array
{
    return ["$prefijo 1", "$prefijo 2", "$prefijo 3", "$prefijo 4"];
}

it('crea el registro con cabecera, versión 1, autor y orden por posición', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);

    $registro = preguntasRepo()->crear($competencia->id, cuatroTextos(), 9);

    expect($registro->version)->toBe(1)
        ->and($registro->creadoPor)->toBe(9)
        ->and($registro->modificadoPor)->toBeNull()
        ->and($registro->preguntas)->toBe(cuatroTextos())
        ->and($registro->competencia->nombre)->toBe('Liderazgo');
    expect(PreguntaModel::query()->count())->toBe(4)
        ->and(PreguntaModel::query()->orderBy('orden')->pluck('orden')->all())->toBe([1, 2, 3, 4])
        ->and(PreguntaModel::query()->where('created_by', 9)->count())->toBe(4);
});

it('no permite dos registros para la misma competencia', function () {
    $competencia = CompetenciaModel::factory()->create();
    preguntasRepo()->crear($competencia->id, cuatroTextos(), null);

    expect(fn () => preguntasRepo()->crear($competencia->id, cuatroTextos(), null))
        ->toThrow(CompetenciaYaTienePreguntas::class);
});

it('el índice único de la cabecera frena un segundo insert para la misma competencia', function () {
    $competencia = CompetenciaModel::factory()->create();
    RegistroPreguntasModel::query()->create(['competencia_id' => $competencia->id]);

    expect(fn () => RegistroPreguntasModel::query()->create(['competencia_id' => $competencia->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza una competencia inexistente o inactiva y no deja nada a medias', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();

    expect(fn () => preguntasRepo()->crear($inactiva->id, cuatroTextos(), null))->toThrow(CompetenciaNoDisponible::class)
        ->and(fn () => preguntasRepo()->crear(999, cuatroTextos(), null))->toThrow(CompetenciaNoDisponible::class)
        ->and(RegistroPreguntasModel::query()->count())->toBe(0)
        ->and(PreguntaModel::query()->count())->toBe(0);
});

it('el número de pregunta es único dentro del registro', function () {
    $competencia = CompetenciaModel::factory()->create();
    preguntasRepo()->crear($competencia->id, cuatroTextos(), null);
    $fila = PreguntaModel::query()->first();

    expect(fn () => PreguntaModel::query()->create([
        'registro_id' => $fila->registro_id,
        'orden' => $fila->orden,
        'pregunta' => 'Repetida',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('no hay cascada: no se puede borrar una competencia ni un registro con preguntas', function () {
    $competencia = CompetenciaModel::factory()->create();
    preguntasRepo()->crear($competencia->id, cuatroTextos(), null);

    expect(fn () => DB::table('competencias')->where('id', $competencia->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('competencia_pregunta_registros')->where('competencia_id', $competencia->id)->delete())
        ->toThrow(QueryException::class);
});

it('edita con cambios: sube la versión, fija quién editó, conserva created_by y solo cambia las filas distintas', function () {
    $competencia = CompetenciaModel::factory()->create();
    preguntasRepo()->crear($competencia->id, cuatroTextos(), 1);
    $antes = PreguntaModel::query()->orderBy('orden')->get()->keyBy('orden');

    $nuevas = cuatroTextos();
    $nuevas[2] = 'Pregunta 3 cambiada';
    $registro = preguntasRepo()->actualizar($competencia->id, 1, $nuevas, 2);

    expect($registro->version)->toBe(2)
        ->and($registro->modificadoPor)->toBe(2)
        ->and($registro->creadoPor)->toBe(1)
        ->and($registro->preguntas)->toBe($nuevas);
    $despues = PreguntaModel::query()->orderBy('orden')->get()->keyBy('orden');
    expect($despues)->toHaveCount(4)
        ->and($despues[3]->updated_by)->toBe(2)
        ->and($despues[3]->created_by)->toBe(1)
        ->and($despues[1]->updated_by)->toBeNull()
        ->and($despues[1]->id)->toBe($antes[1]->id);
});

it('guardar sin cambios reales no modifica nada, ni con espacios alrededor', function () {
    $competencia = CompetenciaModel::factory()->create();
    preguntasRepo()->crear($competencia->id, cuatroTextos(), 1);
    $antes = RegistroPreguntasModel::query()->first();
    $this->travel(5)->minutes();

    $conEspacios = array_map(fn (string $t) => "  $t ", cuatroTextos());
    $registro = preguntasRepo()->actualizar($competencia->id, 1, $conEspacios, 2);

    $despues = RegistroPreguntasModel::query()->first();
    expect($registro->version)->toBe(1)
        ->and($despues->version)->toBe(1)
        ->and($despues->updated_by)->toBeNull()
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});

it('rechaza actualizar con una versión desactualizada o sin registro', function () {
    $competencia = CompetenciaModel::factory()->create();
    preguntasRepo()->crear($competencia->id, cuatroTextos(), null);
    preguntasRepo()->actualizar($competencia->id, 1, cuatroTextos('Otra'), null);

    expect(fn () => preguntasRepo()->actualizar($competencia->id, 1, cuatroTextos('Nueva'), null))
        ->toThrow(RegistroModificado::class)
        ->and(fn () => preguntasRepo()->actualizar(999, 1, cuatroTextos(), null))
        ->toThrow(PreguntasNoEncontradas::class);
    expect(RegistroPreguntasModel::query()->first()->version)->toBe(2);
});

it('el estado y los datos de la competencia se leen en vivo, no se almacenan', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original', 'tipo' => 'Cardinal']);
    preguntasRepo()->crear($competencia->id, cuatroTextos(), null);

    $competencia->forceFill(['nombre' => 'Cambiado', 'nombre_clave' => 'cambiado', 'tipo' => 'Especifica', 'estado' => 'Inactivo'])->save();
    $registro = preguntasRepo()->obtener($competencia->id);

    expect($registro->competencia->nombre)->toBe('Cambiado')
        ->and($registro->competencia->tipo)->toBe(TipoCompetencia::Especifica)
        ->and($registro->estado())->toBe(EstadoCompetencia::Inactivo)
        ->and(Schema::hasColumn('competencia_pregunta_registros', 'estado'))->toBeFalse()
        ->and(Schema::hasColumn('competencia_pregunta_registros', 'nombre'))->toBeFalse();
});

it('lista las competencias disponibles: activas, sin registro y ordenadas por nombre', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    registroPreguntasDe(['nombre' => 'Con registro']);

    expect(array_map(fn ($c) => $c->nombre, preguntasRepo()->disponibles()))->toBe(['Alfa', 'Zeta']);
});

it('la fábrica crea la cabecera y las cuatro preguntas', function () {
    $competencia = registroPreguntasDe();

    expect(preguntasRepo()->obtener($competencia->id)->preguntas)->toHaveCount(4);
});
