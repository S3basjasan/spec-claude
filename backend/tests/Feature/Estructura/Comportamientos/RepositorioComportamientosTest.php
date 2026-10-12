<?php

use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroComportamientosModel;
use Database\Factories\RegistroComportamientosFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

function comportamientosRepo(): ComportamientoRepositoryInterface
{
    return app(ComportamientoRepositoryInterface::class);
}

/** @return array<string, array<int, string>> */
function textos(int $a = 2, int $b = 2, int $c = 2, int $d = 2, string $prefijo = 'Comportamiento'): array
{
    $grado = fn (string $letra, int $n) => array_map(fn (int $i) => "$prefijo $letra$i", range(1, $n));

    return ['A' => $grado('A', $a), 'B' => $grado('B', $b), 'C' => $grado('C', $c), 'D' => $grado('D', $d)];
}

it('crea el registro con cabecera, versión 1, autor y orden por posición', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);

    $registro = comportamientosRepo()->crear($competencia->id, textos(3, 1, 4, 2), 9);

    expect($registro->version)->toBe(1)
        ->and($registro->creadoPor)->toBe(9)
        ->and($registro->modificadoPor)->toBeNull()
        ->and($registro->comportamientos['A'])->toBe(['Comportamiento A1', 'Comportamiento A2', 'Comportamiento A3'])
        ->and($registro->comportamientos['C'])->toHaveCount(4)
        ->and($registro->competencia->nombre)->toBe('Liderazgo');
    expect(ComportamientoModel::query()->count())->toBe(10)
        ->and(ComportamientoModel::query()->where('created_by', 9)->count())->toBe(10);
});

it('no permite dos registros para la misma competencia', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(), null);

    expect(fn () => comportamientosRepo()->crear($competencia->id, textos(), null))
        ->toThrow(CompetenciaYaTieneRegistro::class);
});

it('el índice único de la cabecera frena un segundo insert para la misma competencia', function () {
    $competencia = CompetenciaModel::factory()->create();
    RegistroComportamientosModel::query()->create(['competencia_id' => $competencia->id]);

    expect(fn () => RegistroComportamientosModel::query()->create(['competencia_id' => $competencia->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza una competencia inexistente o inactiva y no deja nada a medias', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();

    expect(fn () => comportamientosRepo()->crear($inactiva->id, textos(), null))->toThrow(CompetenciaNoDisponible::class)
        ->and(fn () => comportamientosRepo()->crear(999, textos(), null))->toThrow(CompetenciaNoDisponible::class)
        ->and(RegistroComportamientosModel::query()->count())->toBe(0)
        ->and(ComportamientoModel::query()->count())->toBe(0);
});

it('el orden de un grado es único: no hay posiciones repetidas', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(), null);
    $fila = ComportamientoModel::query()->first();

    expect(fn () => ComportamientoModel::query()->create([
        'competencia_grado_id' => $fila->competencia_grado_id,
        'orden' => $fila->orden,
        'descripcion' => 'Repetido',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('no hay cascada: no se puede borrar una competencia con registro', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(), null);

    expect(fn () => DB::table('competencias')->where('id', $competencia->id)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('competencia_grados')->where('competencia_id', $competencia->id)->delete())
        ->toThrow(QueryException::class);
});

it('edita con cambios y ajusta las filas: cambia, añade y elimina las sobrantes', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(2, 2, 2, 2), 1);

    $nuevo = textos(3, 1, 2, 2, 'Nuevo');
    $registro = comportamientosRepo()->actualizar($competencia->id, 1, $nuevo, 2);

    expect($registro->version)->toBe(2)
        ->and($registro->modificadoPor)->toBe(2)
        ->and($registro->creadoPor)->toBe(1)
        ->and($registro->comportamientos['A'])->toBe(['Nuevo A1', 'Nuevo A2', 'Nuevo A3'])
        ->and($registro->comportamientos['B'])->toBe(['Nuevo B1']);
    expect(ComportamientoModel::query()->count())->toBe(8)
        ->and(ComportamientoModel::query()->where('created_by', 1)->count())->toBe(7) // las 7 existentes conservan su autor
        ->and(ComportamientoModel::query()->where('created_by', 2)->count())->toBe(1); // la fila nueva A3
});

it('guardar sin cambios reales no modifica nada', function () {
    $competencia = CompetenciaModel::factory()->create();
    $original = comportamientosRepo()->crear($competencia->id, textos(), 1);
    $antes = RegistroComportamientosModel::query()->first();
    $this->travel(5)->minutes();

    $registro = comportamientosRepo()->actualizar($competencia->id, 1, textos(), 2);

    $despues = RegistroComportamientosModel::query()->first();
    expect($registro->version)->toBe($original->version)
        ->and($despues->version)->toBe(1)
        ->and($despues->updated_by)->toBeNull()
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});

it('guardar sin cambios reales ignora los espacios alrededor de los textos', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(), 1);
    $conEspacios = array_map(fn (array $lista) => array_map(fn (string $t) => "  $t ", $lista), textos());

    comportamientosRepo()->actualizar($competencia->id, 1, $conEspacios, 2);

    expect(RegistroComportamientosModel::query()->first()->version)->toBe(1);
});

it('una edición que solo quita comportamientos también registra quién editó', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(4, 4, 4, 4), 1);

    $registro = comportamientosRepo()->actualizar($competencia->id, 1, textos(3, 4, 4, 4), 5);

    expect($registro->modificadoPor)->toBe(5)->and($registro->version)->toBe(2)
        ->and(ComportamientoModel::query()->count())->toBe(15);
});

it('rechaza actualizar con una versión desactualizada o sin registro', function () {
    $competencia = CompetenciaModel::factory()->create();
    comportamientosRepo()->crear($competencia->id, textos(), null);
    comportamientosRepo()->actualizar($competencia->id, 1, textos(1, 1, 1, 1), null);

    expect(fn () => comportamientosRepo()->actualizar($competencia->id, 1, textos(2, 2, 2, 2, 'Otro'), null))
        ->toThrow(RegistroModificado::class)
        ->and(fn () => comportamientosRepo()->actualizar(999, 1, textos(), null))
        ->toThrow(RegistroNoEncontrado::class);
});

it('el estado y los datos de la competencia se leen en vivo, no se almacenan', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original', 'tipo' => 'Cardinal']);
    comportamientosRepo()->crear($competencia->id, textos(), null);

    $competencia->forceFill(['nombre' => 'Cambiado', 'nombre_clave' => 'cambiado', 'tipo' => 'Especifica', 'estado' => 'Inactivo'])->save();
    $registro = comportamientosRepo()->obtener($competencia->id);

    expect($registro->competencia->nombre)->toBe('Cambiado')
        ->and($registro->competencia->tipo)->toBe(TipoCompetencia::Especifica)
        ->and($registro->estado())->toBe(EstadoCompetencia::Inactivo)
        ->and(Schema::hasColumn('competencia_comportamiento_registros', 'estado'))->toBeFalse()
        ->and(Schema::hasColumn('competencia_comportamiento_registros', 'nombre'))->toBeFalse();
});

it('lista las competencias disponibles: activas, sin registro y ordenadas por nombre', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    registroDe(['nombre' => 'Con registro']);

    $disponibles = comportamientosRepo()->disponibles();

    expect(array_map(fn ($c) => $c->nombre, $disponibles))->toBe(['Alfa', 'Zeta']);
});

it('la fábrica crea la cabecera y las filas indicadas', function () {
    $competencia = CompetenciaModel::factory()->create();

    RegistroComportamientosFactory::new()->conComportamientos(['A' => 4, 'B' => 1, 'C' => 3, 'D' => 2])
        ->create(['competencia_id' => $competencia->id]);

    expect(comportamientosRepo()->obtener($competencia->id)->comportamientos['A'])->toHaveCount(4);
});
