<?php

use App\Modules\Compensaciones\Infrastructure\Adapters\UnidadesDesdeEstructura;

// El adaptador real (no los falsos de las demás pruebas) traduce los estados de Estructura («Activa» / «Inactiva»)
// a los del módulo («Activo» / «Inactivo»).
it('lee las unidades de Estructura con los estados del módulo', function () {
    $activa = unidadDe(['nombre' => 'Operaciones']);
    $inactiva = unidadDe(['nombre' => 'Archivo', 'estado' => 'Inactiva']);
    $adaptador = new UnidadesDesdeEstructura;

    $porIds = $adaptador->porIds([$activa->id, $inactiva->id, 99999]);

    expect($porIds)->toHaveCount(2)
        ->and($porIds[$activa->id])->toMatchArray(['id' => $activa->id, 'nombre' => 'Operaciones', 'estado' => 'Activo'])
        ->and($porIds[$inactiva->id]['estado'])->toBe('Inactivo');

    $activas = $adaptador->activas();
    expect(array_column($activas, 'id'))->toContain($activa->id)->not->toContain($inactiva->id)
        ->and(array_unique(array_column($activas, 'estado')))->toBe(['Activo']);
});
