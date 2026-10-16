<?php

use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

it('getDnfRegistradas devuelve las cabeceras de las DNF del tenant sin detalle de brechas', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', 'B']]]);
    dnfDeEjemplo([10 => [[1, 'A', 'D']], 11 => [[1, 'B', 'C']]], 'Departamento', 5);
    tenantDos();
    dnfDeEjemplo([10 => [[1, 'A', 'C']]], tenantId: 2);

    $registradas = DesarrolloFacade::getDnfRegistradas(1);

    expect($registradas)->toHaveCount(2)
        ->and(array_keys($registradas[0]))->toBe(['id', 'codigo', 'fecha', 'alcance', 'colaborador_id', 'departamento_id', 'colaboradores', 'cantidad_brechas', 'prioridad_mas_alta'])
        ->and($registradas[0])->toMatchArray(['codigo' => 'DNF-0002', 'alcance' => 'Departamento', 'departamento_id' => 5, 'colaboradores' => 2, 'cantidad_brechas' => 2, 'prioridad_mas_alta' => 'Critica'])
        ->and($registradas[1])->toMatchArray(['codigo' => 'DNF-0001', 'colaborador_id' => 10, 'cantidad_brechas' => 1, 'prioridad_mas_alta' => 'Alta']);
});

it('getDnfRegistradas aplica los filtros y recorre todas las páginas', function () {
    foreach (range(1, 120) as $i) {
        dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    }
    dnfDeEjemplo([11 => [[1, 'A', 'D']]]);

    expect(DesarrolloFacade::getDnfRegistradas(1))->toHaveCount(121)
        ->and(DesarrolloFacade::getDnfRegistradas(1, ['colaborador_id' => 11]))->toHaveCount(1)
        ->and(DesarrolloFacade::getDnfRegistradas(1, ['prioridad' => 'Critica']))->toHaveCount(1)
        ->and(DesarrolloFacade::getDnfRegistradas(2))->toBe([]);
});
