<?php

use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('describe una posición con su puesto y unidad vigentes y jefatura null', function () {
    $puesto = puestoDe(['nombre' => 'Analista contable']);
    $unidad = unidadDe(['nombre' => 'Finanzas']);
    $posicion = posicionAsignable(['puesto_id' => $puesto->id, 'unidad_organizacional_id' => $unidad->id]);

    $datos = EstructuraFacade::getPosicion($posicion->id);

    expect($datos)->toMatchArray([
        'id' => $posicion->id, 'tenant_id' => 1, 'estado' => 'Vacante', 'puesto' => 'Analista contable',
        'puesto_activo' => true, 'unidad' => 'Finanzas', 'unidad_activa' => true, 'jefatura' => null,
    ])->and(EstructuraFacade::getPosicion(999999))->toBeNull();
});

it('indica el motivo de cada posición no asignable', function () {
    $buena = posicionAsignable();
    $suprimida = posicionAsignable(['estado' => 'Suprimida']);
    $puestoInactivo = posicionAsignable(['puesto_id' => puestoDe(['estado' => 'Inactivo'])->id]);
    $unidadInactiva = posicionAsignable(['unidad_organizacional_id' => unidadDe(['estado' => 'Inactiva'])->id]);
    $ajena = posicionAsignable([], 2);

    expect(EstructuraFacade::posicionEsAsignable($buena->id, 1))->toBe(['asignable' => true, 'motivo' => null])
        ->and(EstructuraFacade::posicionEsAsignable($suprimida->id, 1)['motivo'])->toBe('suprimida')
        ->and(EstructuraFacade::posicionEsAsignable($puestoInactivo->id, 1)['motivo'])->toBe('puesto_inactivo')
        ->and(EstructuraFacade::posicionEsAsignable($unidadInactiva->id, 1)['motivo'])->toBe('unidad_inactiva')
        ->and(EstructuraFacade::posicionEsAsignable($ajena->id, 1)['motivo'])->toBe('otro_tenant')
        ->and(EstructuraFacade::posicionEsAsignable(999999, 1)['motivo'])->toBe('inexistente');
});

it('lista solo las asignables del tenant, con búsqueda sin tildes y paginación', function () {
    $gestion = posicionAsignable(['puesto_id' => puestoDe(['nombre' => 'Gestión documental'])->id]);
    posicionAsignable(['puesto_id' => puestoDe(['nombre' => 'Contador'])->id]);
    posicionAsignable(['estado' => 'Suprimida']);
    posicionAsignable([], 2);

    $todas = EstructuraFacade::posicionesAsignables(1, null, 1, 10);
    $busqueda = EstructuraFacade::posicionesAsignables(1, 'GESTION', 1, 10);
    $pagina = EstructuraFacade::posicionesAsignables(1, null, 2, 1);

    expect($todas['total'])->toBe(2)
        ->and(array_column($busqueda['items'], 'id'))->toBe([$gestion->id])
        ->and($pagina['items'])->toHaveCount(1)->and($pagina['total'])->toBe(2);
});

it('la posición Ocupada o Vacante guardada no cambia su elegibilidad', function () {
    $ocupada = posicionAsignable(['estado' => 'Ocupada']);
    $vacante = posicionAsignable(['estado' => 'Vacante']);

    expect(EstructuraFacade::posicionEsAsignable($ocupada->id, 1)['asignable'])->toBeTrue()
        ->and(EstructuraFacade::posicionEsAsignable($vacante->id, 1)['asignable'])->toBeTrue();
});

it('resuelve posiciones por puesto y por unidad', function () {
    $puesto = puestoDe(['nombre' => 'Jefe de talento']);
    $unidad = unidadDe();
    $posicion = posicionAsignable(['puesto_id' => $puesto->id, 'unidad_organizacional_id' => $unidad->id]);

    expect(EstructuraFacade::getPosicionesPorPuesto('talento'))->toBe([$posicion->id])
        ->and(EstructuraFacade::getPosicionesPorUnidad($unidad->id))->toBe([$posicion->id]);
});
