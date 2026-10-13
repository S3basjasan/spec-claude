<?php

use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\TipoFuncion;
use App\Modules\Estructura\Domain\ValueObjects\TipoRequisito;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoFuncionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoRequisitoModel;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

function puestosRepo(): PuestoRepositoryInterface
{
    return app(PuestoRepositoryInterface::class);
}

// ----------------------------------------------------------- Restricciones

it('asigna el código PU-NNN y no permite repetirlo', function () {
    $primero = puestoDe(['nombre' => 'Analista']);
    $segundo = puestoDe(['nombre' => 'Jefe']);

    expect($primero->fresh()->codigo)->toBe(sprintf('PU-%03d', $primero->id))
        ->and($segundo->fresh()->codigo)->toBe(sprintf('PU-%03d', $segundo->id));

    expect(fn () => $segundo->forceFill(['codigo' => $primero->codigo])->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('no permite dos puestos con el mismo nombre sin distinguir mayúsculas ni espacios alrededor', function () {
    puestoDe(['nombre' => 'Analista de datos']);

    expect(fn () => puestoDe(['nombre' => '  ANALISTA DE DATOS  ']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('no permite repetir el orden de una función ni una competencia dentro del mismo puesto', function () {
    $puesto = puestoDe();
    $competencia = $puesto->competenciasAsignadas()->first();

    expect(fn () => PuestoFuncionModel::query()->create(['puesto_id' => $puesto->id, 'descripcion' => 'Otra', 'tipo' => 'Esencial', 'orden' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => PuestoCompetenciaModel::query()->create(['puesto_id' => $puesto->id, 'competencia_id' => $competencia->competencia_id, 'grado_requerido' => 'A']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('no hay borrado en cascada: un puesto con listas y una competencia asignada no se pueden borrar', function () {
    $puesto = puestoDe();
    $competenciaId = $puesto->competenciasAsignadas()->first()->competencia_id;

    expect(fn () => $puesto->delete())->toThrow(QueryException::class);
    expect(fn () => CompetenciaModel::query()->whereKey($competenciaId)->delete())->toThrow(QueryException::class);
    expect(PuestoModel::query()->count())->toBe(1);
});

// ------------------------------------------------------------------ Lecturas

it('lista por nombre ascendente sin cargar las listas y pagina', function () {
    puestoDe(['nombre' => 'Zeta']);
    puestoDe(['nombre' => 'Alfa']);
    puestoDe(['nombre' => 'Mu']);

    $pagina = puestosRepo()->listar(null, null, 1, 2);
    $segunda = puestosRepo()->listar(null, null, 2, 2);

    expect($pagina->total)->toBe(3)
        ->and(array_map(fn ($p) => $p->nombre, $pagina->items))->toBe(['Alfa', 'Mu'])
        ->and(array_map(fn ($p) => $p->nombre, $segunda->items))->toBe(['Zeta'])
        ->and($pagina->items[0]->funciones)->toBe([])
        ->and($pagina->items[0]->competencias)->toBe([]);
});

it('busca por código y por nombre sin distinguir tildes ni mayúsculas', function () {
    $gestion = puestoDe(['nombre' => 'Ejecutivo de gestión comercial']);
    puestoDe(['nombre' => 'Analista']);

    $porNombre = puestosRepo()->listar('GESTION', null, 1, 10);
    $conTilde = puestosRepo()->listar('Gestión', null, 1, 10);
    $porCodigo = puestosRepo()->listar(strtolower($gestion->fresh()->codigo), null, 1, 10);

    expect(array_map(fn ($p) => $p->nombre, $porNombre->items))->toBe(['Ejecutivo de gestión comercial'])
        ->and($conTilde->total)->toBe(1)
        ->and($porCodigo->total)->toBe(1)
        ->and($porCodigo->items[0]->id)->toBe($gestion->id);
});

it('trata % y _ de la búsqueda como texto literal', function () {
    puestoDe(['nombre' => 'Cobertura 100% regional']);
    puestoDe(['nombre' => 'Analista de datos']);
    puestoDe(['nombre' => 'Analista_de_datos']);

    expect(puestosRepo()->listar('100%', null, 1, 10)->total)->toBe(1)
        ->and(puestosRepo()->listar('a_d', null, 1, 10)->total)->toBe(1)
        ->and(puestosRepo()->listar('%', null, 1, 10)->total)->toBe(1);
});

it('filtra por estado', function () {
    puestoDe(['nombre' => 'Activo uno']);
    puestoDe(['nombre' => 'Inactivo uno', 'estado' => 'Inactivo']);

    expect(puestosRepo()->listar(null, EstadoPuesto::Activo, 1, 10)->total)->toBe(1)
        ->and(puestosRepo()->listar(null, EstadoPuesto::Inactivo, 1, 10)->total)->toBe(1)
        ->and(puestosRepo()->listar(null, null, 1, 10)->total)->toBe(2);
});

it('obtiene funciones por orden, requisitos por tipo y competencias con datos en vivo', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    PuestoFuncionModel::query()->create(['puesto_id' => $puesto->id, 'descripcion' => 'Tercera', 'tipo' => 'Complementaria', 'orden' => 3]);
    PuestoRequisitoModel::query()->create(['puesto_id' => $puesto->id, 'tipo' => 'Capacitacion', 'descripcion' => 'Curso de SQL.']);
    PuestoRequisitoModel::query()->create(['puesto_id' => $puesto->id, 'tipo' => 'Formacion', 'descripcion' => 'Segunda formación.']);
    $competencia = CompetenciaModel::query()->find($puesto->competenciasAsignadas()->first()->competencia_id);
    $competencia->update(['nombre' => 'Nombre nuevo', 'nombre_clave' => 'nombre nuevo']);

    $entidad = puestosRepo()->obtener($puesto->id);

    expect(array_map(fn ($f) => $f->orden, $entidad->funciones))->toBe([1, 2, 3])
        ->and($entidad->funciones[0]->tipo)->toBe(TipoFuncion::Esencial)
        ->and(array_map(fn ($r) => $r->tipo, $entidad->requisitos))->toBe([
            TipoRequisito::Formacion, TipoRequisito::Formacion, TipoRequisito::Experiencia, TipoRequisito::Capacitacion,
        ])
        ->and($entidad->competencias[0]->competencia->nombre)->toBe('Nombre nuevo')
        ->and($entidad->competencias[0]->gradoRequerido)->toBe('B')
        ->and($entidad->codigo)->toBe($puesto->fresh()->codigo)
        ->and(puestosRepo()->obtener(999999))->toBeNull();
});

it('obtiene varios puestos y solo los activos', function () {
    $activo = puestoDe(['nombre' => 'Activo']);
    $inactivo = puestoDe(['nombre' => 'Inactivo', 'estado' => 'Inactivo']);

    expect(array_map(fn ($p) => $p->id, puestosRepo()->obtenerPorIds([$activo->id, $inactivo->id, 999999])))->toBe([$activo->id, $inactivo->id])
        ->and(array_map(fn ($p) => $p->id, puestosRepo()->obtenerActivos()))->toBe([$activo->id]);
});

it('ofrece solo competencias activas con sus cuatro grados y valida también las inactivas', function () {
    $zeta = CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    $alfa = CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    $inactiva = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Inactiva']);

    $disponibles = puestosRepo()->competenciasDisponibles();
    $porIds = puestosRepo()->competenciasPorIds([$zeta->id, $inactiva->id, 999999]);

    expect(array_map(fn ($c) => $c->competencia->nombre, $disponibles))->toBe(['Alfa', 'Zeta'])
        ->and(array_keys($disponibles[0]->grados))->toBe(['A', 'B', 'C', 'D'])
        ->and(array_keys($porIds))->toEqualCanonicalizing([$zeta->id, $inactiva->id])
        ->and($porIds[$inactiva->id]->competencia->estado->value)->toBe('Inactivo')
        ->and($porIds[$zeta->id]->tieneGrado('C'))->toBeTrue()
        ->and($porIds[$zeta->id]->tieneGrado('E'))->toBeFalse()
        ->and($alfa->id)->not->toBe($zeta->id);
});
