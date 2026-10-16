<?php

use App\Modules\Desarrollo\Domain\Exceptions\ConfiguracionModificada;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaCursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialFormativoModel;
use Illuminate\Database\QueryException;

function repositorioFormativo(): CatalogoFormativoRepositoryInterface
{
    return app(CatalogoFormativoRepositoryInterface::class);
}

it('la primera configuración crea la fila con versión 1 y escribe el historial', function () {
    $curso = CursoModel::factory()->create(['nombre' => 'Curso uno']);

    $config = repositorioFormativo()->guardarConfiguracion(10, 0, new DatosConfiguracion(null, [$curso->id], []), 7);

    expect($config->version)->toBe(1)
        ->and($config->actualizadoPor)->toBe(7)
        ->and($config->cursos)->toHaveCount(1)
        ->and(HistorialFormativoModel::query()->first()->valor_nuevo)->toBe('Curso uno')
        ->and(HistorialFormativoModel::query()->first()->user_id)->toBe(7);
});

it('una configuración nunca guardada es vacía y de versión 0', function () {
    $config = repositorioFormativo()->configuracionDe(55);

    expect($config->version)->toBe(0)->and($config->cursos)->toBe([])->and($config->areaFormativaId)->toBeNull();
});

it('configuracionesDe incluye una entrada vacía por cada id pedido', function () {
    $curso = CursoModel::factory()->create();
    configurarFormativa(1, null, [$curso->id]);

    $mapa = repositorioFormativo()->configuracionesDe([1, 2]);

    expect(array_keys($mapa))->toBe([1, 2])
        ->and($mapa[1]->cursos)->toHaveCount(1)
        ->and($mapa[2]->version)->toBe(0);
});

it('sin cambios reales no escribe nada y no sube la versión', function () {
    $curso = CursoModel::factory()->create();
    configurarFormativa(3, null, [$curso->id]);

    repositorioFormativo()->guardarConfiguracion(3, 1, new DatosConfiguracion(null, [$curso->id], []), 1);

    expect(HistorialFormativoModel::query()->count())->toBe(0)
        ->and(CompetenciaFormativaModel::query()->first()->version)->toBe(1);
});

it('si la versión no coincide lanza ConfiguracionModificada', function () {
    configurarFormativa(3, null, [], [], 4);

    repositorioFormativo()->guardarConfiguracion(3, 1, new DatosConfiguracion(null, [], []), 1);
})->throws(ConfiguracionModificada::class);

it('la restricción única impide duplicar un vínculo', function () {
    $curso = CursoModel::factory()->create();
    CompetenciaCursoModel::query()->create(['competencia_id' => 1, 'curso_id' => $curso->id]);

    CompetenciaCursoModel::query()->create(['competencia_id' => 1, 'curso_id' => $curso->id]);
})->throws(QueryException::class);

it('no se puede eliminar un curso que tiene vínculos (sin cascada de borrado)', function () {
    $curso = CursoModel::factory()->create();
    CompetenciaCursoModel::query()->create(['competencia_id' => 1, 'curso_id' => $curso->id]);

    $curso->delete();
})->throws(QueryException::class);

it('el guardado es atómico: si falla a mitad no queda nada guardado', function () {
    $curso = CursoModel::factory()->create();
    // El micro-contenido 9999 no existe: la FK falla después de haber insertado el curso.
    try {
        repositorioFormativo()->guardarConfiguracion(8, 0, new DatosConfiguracion(null, [$curso->id], [9999]), 1);
    } catch (Throwable) {
    }

    expect(CompetenciaCursoModel::query()->count())->toBe(0)
        ->and(CompetenciaFormativaModel::query()->count())->toBe(0)
        ->and(HistorialFormativoModel::query()->count())->toBe(0);
});
