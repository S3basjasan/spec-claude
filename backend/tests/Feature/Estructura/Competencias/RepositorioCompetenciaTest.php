<?php

use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

function repositorio(): CompetenciaRepositoryInterface
{
    return app(CompetenciaRepositoryInterface::class);
}

/** @return array<string, string> */
function gradosDe(string $prefijo = 'Grado'): array
{
    return ['A' => "$prefijo A", 'B' => "$prefijo B", 'C' => "$prefijo C", 'D' => "$prefijo D"];
}

it('asigna el código COMP-NNNN al crear, nace Activo y guarda los cuatro grados y el autor', function () {
    $primera = repositorio()->crear('Liderazgo', 'Guiar a otros.', TipoCompetencia::Cardinal, gradosDe(), 7);
    $segunda = repositorio()->crear('Gestión', 'Gestionar proyectos.', TipoCompetencia::Especifica, gradosDe(), null);

    expect($primera->codigo)->toBe('COMP-0001')
        ->and($segunda->codigo)->toBe('COMP-0002')
        ->and($primera->estado)->toBe(EstadoCompetencia::Activo)
        ->and($primera->version)->toBe(1)
        ->and($primera->creadoPor)->toBe(7)
        ->and($primera->modificadoPor)->toBeNull()
        ->and($segunda->creadoPor)->toBeNull()
        ->and($primera->grados)->toBe(gradosDe());
});

it('rechaza un nombre duplicado sin distinguir mayúsculas, con acentos ni estado', function () {
    $primera = repositorio()->crear('Innovación', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);
    repositorio()->cambiarEstado($primera->id, EstadoCompetencia::Inactivo, null);

    expect(fn () => repositorio()->crear('INNOVACIÓN', 'Otra.', TipoCompetencia::Cardinal, gradosDe(), null))
        ->toThrow(NombreDuplicado::class)
        ->and(fn () => repositorio()->crear('  innovación ', 'Otra.', TipoCompetencia::Cardinal, gradosDe(), null))
        ->toThrow(NombreDuplicado::class);
});

it('no deja una competencia a medias si falla la creación', function () {
    repositorio()->crear('Repetida', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);

    try {
        repositorio()->crear('repetida', 'Otra.', TipoCompetencia::Cardinal, gradosDe(), null);
    } catch (NombreDuplicado) {
    }

    expect(CompetenciaModel::query()->count())->toBe(1)
        ->and(CompetenciaGradoModel::query()->count())->toBe(4);
});

it('permite un solo grado de cada letra por competencia', function () {
    $competencia = repositorio()->crear('Única', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);

    CompetenciaGradoModel::query()->create(['competencia_id' => $competencia->id, 'grado' => 'A', 'descripcion' => 'Repetido']);
})->throws(UniqueConstraintViolationException::class);

it('no permite borrar una competencia: sus grados la protegen (sin cascada)', function () {
    $competencia = repositorio()->crear('Permanente', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);

    expect(fn () => CompetenciaModel::query()->whereKey($competencia->id)->delete())
        ->toThrow(QueryException::class)
        ->and(CompetenciaModel::query()->count())->toBe(1)
        ->and(CompetenciaGradoModel::query()->count())->toBe(4);
});

it('actualiza incrementando la versión, conservando código y estado y guardando quién editó', function () {
    $competencia = repositorio()->crear('Original', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), 1);
    repositorio()->cambiarEstado($competencia->id, EstadoCompetencia::Inactivo, 1);

    $actualizada = repositorio()->actualizar($competencia->id, 1, 'Cambiada', 'Nueva.', TipoCompetencia::Especifica, gradosDe('Nuevo'), 2);

    expect($actualizada->version)->toBe(2)
        ->and($actualizada->codigo)->toBe($competencia->codigo)
        ->and($actualizada->estado)->toBe(EstadoCompetencia::Inactivo)
        ->and($actualizada->creadoPor)->toBe(1)
        ->and($actualizada->modificadoPor)->toBe(2)
        ->and($actualizada->nombre)->toBe('Cambiada')
        ->and($actualizada->tipo)->toBe(TipoCompetencia::Especifica)
        ->and($actualizada->grados['D'])->toBe('Nuevo D');
});

it('rechaza actualizar con una versión desactualizada', function () {
    $competencia = repositorio()->crear('Original', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);
    repositorio()->actualizar($competencia->id, 1, 'Original', 'Primera edición.', TipoCompetencia::Cardinal, gradosDe(), null);

    repositorio()->actualizar($competencia->id, 1, 'Original', 'Segunda edición.', TipoCompetencia::Cardinal, gradosDe(), null);
})->throws(ConflictoDeVersion::class);

it('informa que no existe al actualizar o cambiar el estado de un id inexistente', function () {
    expect(fn () => repositorio()->actualizar(999, 1, 'X', 'X', TipoCompetencia::Cardinal, gradosDe(), null))
        ->toThrow(CompetenciaNoEncontrada::class)
        ->and(fn () => repositorio()->cambiarEstado(999, EstadoCompetencia::Inactivo, null))
        ->toThrow(CompetenciaNoEncontrada::class);
});

it('rechaza renombrar a un nombre existente', function () {
    repositorio()->crear('Primera', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);
    $segunda = repositorio()->crear('Segunda', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);

    repositorio()->actualizar($segunda->id, 1, 'primera', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);
})->throws(NombreDuplicado::class);

it('cambia el estado registrando el historial sin tocar la versión ni quién editó', function () {
    $competencia = repositorio()->crear('Estable', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), 1);

    $inactiva = repositorio()->cambiarEstado($competencia->id, EstadoCompetencia::Inactivo, 5);
    $activa = repositorio()->cambiarEstado($competencia->id, EstadoCompetencia::Activo, 6);

    expect($inactiva->estado)->toBe(EstadoCompetencia::Inactivo)
        ->and($inactiva->version)->toBe(1)
        ->and($inactiva->modificadoPor)->toBeNull()
        ->and($activa->estado)->toBe(EstadoCompetencia::Activo);

    $historial = CompetenciaEstadoHistorialModel::query()->orderBy('id')->get();
    expect($historial)->toHaveCount(2)
        ->and($historial[0]->estado_anterior)->toBe('Activo')
        ->and($historial[0]->estado_nuevo)->toBe('Inactivo')
        ->and($historial[0]->user_id)->toBe(5)
        ->and($historial[1]->estado_anterior)->toBe('Inactivo')
        ->and($historial[1]->estado_nuevo)->toBe('Activo')
        ->and($historial[1]->user_id)->toBe(6)
        ->and($historial[0]->created_at)->not->toBeNull();
});

it('no registra historial si la competencia ya está en ese estado', function () {
    $competencia = repositorio()->crear('Estable', 'Descripción.', TipoCompetencia::Cardinal, gradosDe(), null);

    expect(fn () => repositorio()->cambiarEstado($competencia->id, EstadoCompetencia::Activo, null))
        ->toThrow(EstadoSinCambio::class)
        ->and(CompetenciaEstadoHistorialModel::query()->count())->toBe(0);
});

it('devuelve solo las activas con sus grados ordenadas por nombre', function () {
    $b = repositorio()->crear('Beta', 'D.', TipoCompetencia::Cardinal, gradosDe(), null);
    repositorio()->crear('Alfa', 'D.', TipoCompetencia::Cardinal, gradosDe(), null);
    repositorio()->cambiarEstado($b->id, EstadoCompetencia::Inactivo, null);

    $activas = repositorio()->obtenerActivas();

    expect($activas)->toHaveCount(1)
        ->and($activas[0]->nombre)->toBe('Alfa')
        ->and($activas[0]->grados)->toHaveCount(4);
});
