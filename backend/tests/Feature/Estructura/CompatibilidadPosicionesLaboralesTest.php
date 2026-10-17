<?php

use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\EstructuraFacadeLector;
use App\Modules\GestionPersonal\Infrastructure\Adapters\PosicionesAsignablesEstructura;

it('mantiene la lectura laboral y el detalle de estructura para la misma posición', function () {
    $puesto = puestoDe(['nombre' => 'Analista contable']);
    $unidad = unidadDe(['nombre' => 'Finanzas']);
    $posicion = posicionAsignable(['puesto_id' => $puesto->id, 'unidad_organizacional_id' => $unidad->id]);

    $detalle = EstructuraFacade::getPosicion($posicion->id);
    $laboral = EstructuraFacade::getPosicionesLaboralesPorIds([$posicion->id]);
    $verificacion = app(PosicionesAsignablesEstructura::class)->verificar($posicion->id, 1);

    expect($detalle['puesto']['nombre'])->toBe('Analista contable')
        ->and($laboral[$posicion->id]['puesto'])->toBe('Analista contable')
        ->and($laboral[$posicion->id]['unidad'])->toBe('Finanzas')
        ->and($verificacion['asignable'])->toBeTrue()
        ->and($verificacion['posicion']['puesto'])->toBe('Analista contable');

    expect(app(EstructuraFacadeLector::class)->posiciones([$posicion->id]))
        ->toEqual(Lectura::disponible([
            $posicion->id => ['puesto' => 'Analista contable', 'unidad_id' => $unidad->id, 'unidad' => 'Finanzas', 'jefatura' => null],
        ]));
});
