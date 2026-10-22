<?php

use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\EstructuraFacadeLector;
use App\Modules\GestionPersonal\Infrastructure\Adapters\PosicionesAsignablesEstructura;

it('mantiene las lecturas individual y por lote y la verificación de la misma posición', function () {
    $puesto = puestoDe(['nombre' => 'Analista contable']);
    $unidad = unidadDe(['nombre' => 'Finanzas']);
    $posicion = posicionAsignable(['puesto_id' => $puesto->id, 'unidad_organizacional_id' => $unidad->id]);

    $detalle = EstructuraFacade::getPosicionDeGestion($posicion->id);
    $laboral = EstructuraFacade::getPosicionesDeGestionPorIds([$posicion->id]);
    $verificacion = app(PosicionesAsignablesEstructura::class)->verificar($posicion->id, 1);

    expect($detalle['puesto'])->toBe('Analista contable')
        ->and($laboral[$posicion->id]['puesto'])->toBe('Analista contable')
        ->and($laboral[$posicion->id]['unidad'])->toBe('Finanzas')
        ->and($verificacion['asignable'])->toBeTrue()
        ->and($verificacion['posicion']['puesto'])->toBe('Analista contable');

    expect(app(EstructuraFacadeLector::class)->posiciones([$posicion->id]))
        ->toEqual(Lectura::disponible([
            $posicion->id => ['puesto' => 'Analista contable', 'unidad_id' => $unidad->id, 'unidad' => 'Finanzas', 'jefatura' => null],
        ]));
});
