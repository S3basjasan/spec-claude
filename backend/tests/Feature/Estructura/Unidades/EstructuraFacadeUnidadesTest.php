<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('devuelve una unidad activa e inactiva con tipo, padre y nivel, sin auditoría', function () {
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['tecnologia'])->inactiva()->create(['nombre' => 'Operaciones']);

    $activa = EstructuraFacade::getUnidad($a['reclutamiento']->id);
    $retirada = EstructuraFacade::getUnidad($inactiva->id);

    expect($activa['nombre'])->toBe('Reclutamiento')
        ->and($activa['nivel'])->toBe(2)
        ->and($activa['unidad_padre']['codigo'])->toBe($a['talento']->codigo)
        ->and($activa['tipo']['nombre'])->toBe('Departamento')
        ->and($retirada['estado'])->toBe('Inactiva')
        ->and($retirada['fecha_supresion'])->toBe(now()->toDateString())
        ->and($activa)->not->toHaveKeys(['auditoria', 'created_by', 'updated_by']);
});

it('devuelve null para una unidad que no existe', function () {
    expect(EstructuraFacade::getUnidad(999))->toBeNull()->and(EstructuraFacade::existeUnidad(999))->toBeFalse();
});

it('consulta varias unidades a la vez, incluidas las inactivas, y omite las que no existen', function () {
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create();

    $resultado = EstructuraFacade::getUnidadesPorIds([$a['talento']->id, $inactiva->id, 999, $a['talento']->id]);

    expect($resultado)->toHaveCount(2)->and(EstructuraFacade::getUnidadesPorIds([]))->toBe([]);
});

it('solo las unidades activas sirven para nuevas asignaciones', function () {
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create();

    expect(EstructuraFacade::unidadEstaActiva($a['talento']->id))->toBeTrue()
        ->and(EstructuraFacade::unidadEstaActiva($inactiva->id))->toBeFalse()
        ->and(EstructuraFacade::unidadEstaActiva(999))->toBeFalse()
        ->and(EstructuraFacade::existeUnidad($inactiva->id))->toBeTrue();
});

it('devuelve las unidades activas en orden jerárquico y el árbol completo con las inactivas', function () {
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Operaciones']);

    $activas = EstructuraFacade::getUnidadesActivas();
    $arbol = EstructuraFacade::getArbolDeUnidades();

    expect(array_column($activas, 'nombre'))->toBe(['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo'])
        ->and(array_column($activas, 'nivel'))->toBe([0, 1, 2, 1, 2])
        ->and(array_column($arbol, 'nombre'))->toBe(['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo', 'Operaciones'])
        ->and($arbol[5]['estado'])->toBe('Inactiva');
});

it('devuelve solo arreglos, nunca modelos ni entidades', function () {
    $a = arbolDeEjemplo();

    expect(EstructuraFacade::getUnidad($a['raiz']->id))->toBeArray()
        ->and(EstructuraFacade::getArbolDeUnidades()[0])->toBeArray();
});
