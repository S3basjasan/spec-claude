<?php

use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use Illuminate\Support\Facades\DB;

function repoDeEstado(): PuestoRepositoryInterface
{
    return app(PuestoRepositoryInterface::class);
}

it('desactivar es una sola sentencia condicional: comprobar las posiciones y cambiar el estado es atómico', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);

    DB::enableQueryLog();
    repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Inactivo, 5);
    $actualizaciones = array_filter(
        DB::getQueryLog(),
        fn (array $consulta) => str_starts_with(strtolower($consulta['query']), 'update "puestos"'),
    );
    DB::disableQueryLog();

    expect($actualizaciones)->toHaveCount(1)
        ->and(strtolower(array_values($actualizaciones)[0]['query']))->toContain('not exists')
        ->and($puesto->fresh()->estado)->toBe('Inactivo');
});

it('una posición Vacante creada antes impide la desactivación y deja el puesto Activo', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    posicionesDe($puesto, 'Vacante');

    expect(fn () => repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Inactivo, 5))->toThrow(PuestoConPosicionesEnUso::class);
    expect($puesto->fresh()->estado)->toBe('Activo');
});

it('cuenta las posiciones Ocupadas y Vacantes y excluye las Suprimidas', function () {
    $puesto = puestoDe(['nombre' => 'Analista'], ocupadas: 2, vacantes: 1, suprimidas: 5);

    try {
        repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Inactivo, 5);
        $this->fail('Debía rechazar la desactivación.');
    } catch (PuestoConPosicionesEnUso $e) {
        expect($e->cantidad)->toBe(3);
    }
});

it('con solo posiciones Suprimidas se desactiva', function () {
    $puesto = puestoDe(['nombre' => 'Analista'], suprimidas: 2);

    $resultado = repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Inactivo, 5);

    expect($resultado->estado)->toBe(EstadoPuesto::Inactivo);
});

it('dos desactivaciones seguidas dejan una sola fila de estado en el historial', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    $antes = AuditoriaCambioModel::query()->where('entidad_id', $puesto->id)->where('campo', 'estado')->count();

    repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Inactivo, 5);
    repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Inactivo, 6);

    $filas = AuditoriaCambioModel::query()->where('entidad_id', $puesto->id)->where('campo', 'estado')->get();
    expect($filas)->toHaveCount($antes + 1)
        ->and($filas->last()->valor_anterior)->toBe('Activo')
        ->and($filas->last()->valor_nuevo)->toBe('Inactivo')
        ->and($filas->last()->user_id)->toBe(5);
});

it('reactivar escribe Inactivo → Activo y no tiene restricciones, aunque haya posiciones', function () {
    $puesto = puestoDe(['nombre' => 'Analista', 'estado' => 'Inactivo'], ocupadas: 1);

    repoDeEstado()->cambiarEstado($puesto->id, EstadoPuesto::Activo, 7);

    $fila = AuditoriaCambioModel::query()->where('entidad_id', $puesto->id)->where('campo', 'estado')->latest('id')->first();
    expect($puesto->fresh()->estado)->toBe('Activo')
        ->and($fila->valor_anterior)->toBe('Inactivo')
        ->and($fila->valor_nuevo)->toBe('Activo');
});

it('informa cuando el puesto no existe', function () {
    expect(fn () => repoDeEstado()->cambiarEstado(999999, EstadoPuesto::Inactivo, 5))->toThrow(PuestoNoEncontrado::class);
});
