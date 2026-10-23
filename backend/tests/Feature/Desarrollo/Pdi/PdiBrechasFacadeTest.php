<?php

use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

it('ultimaDnfConBrechas devuelve el identificador de la DNF más reciente y sus brechas sin alterar brechasActivas', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'D']]]);
    $ultima = dnfDeEjemplo([10 => [[1, 'B', 'C'], [2, 'B', 'B']]]);

    $r = DesarrolloFacade::ultimaDnfConBrechas(1, 10);

    expect($r['dnf_id'])->toBe($ultima->id)
        ->and($r['brechas'])->toBe([['competencia_id' => 1, 'nivel_requerido' => 'B', 'nivel_actual' => 'C', 'diferencia' => -1, 'prioridad' => 'Media']])
        ->and(DesarrolloFacade::brechasActivas(1, 10))->toBe($r['brechas'])
        ->and(DesarrolloFacade::ultimaDnfConBrechas(1, 99))->toBeNull()
        ->and(DesarrolloFacade::ultimaDnfConBrechas(2, 10))->toBeNull();
});

it('ultimaAdecuacion incluye los niveles y los años de cada brecha sin romper el contrato anterior', function () {
    adecuacionFalsos();
    adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['anios' => [5.0, 3.0], 'tiempo' => 18],
    ], ['experiencia' => 5.0]);

    $brechas = DesarrolloFacade::ultimaAdecuacion(1, 10, 2)['brechas'];

    expect($brechas[0])->toMatchArray(['dimension' => 'Competencia', 'elemento_id' => 1, 'diferencia' => -1.0, 'tiempo_cierre_meses' => 6, 'nivel_actual' => 'C', 'nivel_objetivo' => 'B'])
        ->and($brechas[1])->toMatchArray(['dimension' => 'Experiencia', 'nivel_actual' => null, 'valor_anios' => 3.0, 'requerido_anios' => 5.0]);
});
