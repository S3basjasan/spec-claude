<?php

use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CategoriaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Crea por el repositorio con el cuerpo de datosCompetencia().
 *
 * @param  array<string, mixed>  $cambios
 */
function crearPorRepositorio(array $cambios = [])
{
    $datos = datosCompetencia($cambios);

    return app(CompetenciaRepositoryInterface::class)->crear(
        $datos['nombre'],
        $datos['descripcion'],
        TipoCompetencia::from($datos['tipo']),
        $datos['tipo_grado_id'],
        $datos['categorias'] ?? null,
        $datos['grados'],
        $datos['comportamientos'],
        $datos['preguntas'],
        null,
    );
}

it('asigna el código dentro de la transacción y no lo reutiliza', function () {
    $primera = crearPorRepositorio(['nombre' => 'Primera']);
    $segunda = crearPorRepositorio(['nombre' => 'Segunda']);

    expect($primera->codigo)->toBe('COMP-0001')
        ->and($segunda->codigo)->toBe('COMP-0002');
});

it('dos creaciones seguidas con el mismo nombre dejan un solo registro y no repiten el código', function () {
    crearPorRepositorio(['nombre' => 'Única']);

    expect(fn () => crearPorRepositorio(['nombre' => ' única ']))->toThrow(NombreDuplicado::class);
    expect(CompetenciaModel::query()->count())->toBe(1)
        ->and(CompetenciaModel::query()->pluck('codigo')->unique()->count())->toBe(1);
});

it('hace única la clave del nombre sin distinguir mayúsculas, tildes ni espacios', function () {
    crearPorRepositorio(['nombre' => 'Orientación']);

    expect(CompetenciaModel::query()->value('nombre_clave'))->toBe('orientacion')
        ->and(fn () => DB::table('competencias')->insert([
            'codigo' => 'COMP-9999', 'nombre' => 'Otra', 'nombre_clave' => 'orientacion', 'descripcion' => 'd',
            'tipo' => 'Cardinal', 'estado' => 'Activo', 'version' => 1,
        ]))->toThrow(QueryException::class);
});

it('guarda los cuatro grados con la escala elegida y sin borrado en cascada', function () {
    $competencia = crearPorRepositorio(['tipo_grado_id' => escalaId('Números')]);

    expect(CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->count())->toBe(4)
        ->and(array_map(fn ($g) => $g->etiqueta, $competencia->gradosDetalle))->toBe(['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'])
        ->and(fn () => DB::table('competencia_grados')->where('competencia_id', $competencia->id)->delete())->toThrow(QueryException::class);
});

it('rechaza una escala inexistente sin dejar datos a medias', function () {
    expect(fn () => crearPorRepositorio(['tipo_grado_id' => 99999]))->toThrow(EscalaNoEncontrada::class);

    expect(CompetenciaModel::query()->count())->toBe(0);
});

it('hace única cada categoría dentro de la competencia', function () {
    $competencia = crearPorRepositorio(['tipo_grado_id' => escalaId('Categorías'), 'categorias' => ['Alta', 'Media', 'Baja', 'Mínima']]);

    expect(CategoriaModel::query()->where('competencia_id', $competencia->id)->count())->toBe(4)
        ->and(fn () => CategoriaModel::query()->create([
            'competencia_id' => $competencia->id, 'orden' => 1, 'nombre' => 'Otra', 'nombre_clave' => 'otra',
        ]))->toThrow(QueryException::class)
        ->and(fn () => CategoriaModel::query()->create([
            'competencia_id' => $competencia->id, 'orden' => 9, 'nombre' => 'ALTA', 'nombre_clave' => 'alta',
        ]))->toThrow(QueryException::class);
});

it('permite intercambiar los nombres de las categorías al editarlas', function () {
    $competencia = crearPorRepositorio(['tipo_grado_id' => escalaId('Categorías'), 'categorias' => ['Alta', 'Media', 'Baja', 'Mínima']]);
    $datos = datosCompetencia();

    $editada = app(CompetenciaRepositoryInterface::class)->actualizar(
        $competencia->id,
        $competencia->version,
        $competencia->nombre,
        $competencia->descripcion,
        $competencia->tipo,
        escalaId('Categorías'),
        ['Media', 'Alta', 'Mínima', 'Baja'],
        $datos['grados'],
        null,
    );

    expect($editada->categorias)->toBe([1 => 'Media', 2 => 'Alta', 3 => 'Mínima', 4 => 'Baja']);
});
