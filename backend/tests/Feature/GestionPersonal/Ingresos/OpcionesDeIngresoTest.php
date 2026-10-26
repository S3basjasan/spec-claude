<?php

use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;

it('ofrece los 4 tipos de contrato y las posiciones elegibles sin las ocupadas desde esa fecha', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '30']);
    $otra = fichaDe(['numero_identificacion' => '31']);
    $libre = posicionAsignable();
    $ocupada = posicionAsignable();
    vinculoDe($otra->id, hoyDelTenant(-100), null, $ocupada->id);

    $respuesta = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant())->assertOk();

    expect(array_column($respuesta->json('data.tipos_contrato'), 'clave'))
        ->toBe(['indefinido', 'plazo_fijo', 'temporal', 'servicios_profesionales'])
        ->and(array_column($respuesta->json('data.posiciones'), 'id'))->toBe([$libre->id])
        ->and($respuesta->json('data.ingreso_programado'))->toBeFalse()
        ->and($respuesta->json('data.conflicto_ficha'))->toBeNull()
        ->and($respuesta->json('data.posiciones.0'))->toHaveKeys(['id', 'puesto', 'unidad', 'jefatura'])
        ->and($respuesta->json('data.posiciones.0.jefatura'))->toBeNull();
});

it('una posición ocupada hasta una fecha futura vuelve a ser elegible desde esa fecha', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '32']);
    $otra = fichaDe(['numero_identificacion' => '33']);
    $posicion = posicionAsignable();
    vinculoDe($otra->id, hoyDelTenant(-100), hoyDelTenant(30), $posicion->id);

    $antes = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant(29))->json('data.posiciones');
    $desde = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant(30))->json('data.posiciones');

    expect($antes)->toBe([])->and(array_column($desde, 'id'))->toBe([$posicion->id]);
});

it('excluye posiciones suprimidas, con puesto o unidad inactivos y de otro tenant', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '34']);
    $buena = posicionAsignable();
    posicionAsignable(['estado' => 'Suprimida']);
    posicionAsignable(['puesto_id' => puestoDe(['estado' => 'Inactivo'])->id]);
    posicionAsignable(['unidad_organizacional_id' => unidadDe(['estado' => 'Inactiva'])->id]);
    posicionAsignable([], 2);

    $ids = array_column($this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant())->json('data.posiciones'), 'id');

    expect($ids)->toBe([$buena->id]);
});

it('busca por puesto o unidad sin distinguir tildes y pagina', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '35']);
    $analista = posicionAsignable(['puesto_id' => puestoDe(['nombre' => 'Analista de gestión'])->id]);
    posicionAsignable(['puesto_id' => puestoDe(['nombre' => 'Contador'])->id]);

    $busqueda = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant().'&q=GESTION')->assertOk();
    $pagina = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant().'&per_page=1&page=2')->assertOk();

    expect(array_column($busqueda->json('data.posiciones'), 'id'))->toBe([$analista->id])
        ->and($pagina->json('meta.total'))->toBe(2)
        ->and($pagina->json('data.posiciones'))->toHaveCount(1);
});

it('marca el ingreso programado y avisa del conflicto de la ficha antes de confirmar', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '36']);
    $posicion = posicionAsignable();
    vinculoDe($ficha->id, hoyDelTenant(-10), null, posicionAsignable()->id);

    $respuesta = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant(15))->assertOk();

    expect($respuesta->json('data.ingreso_programado'))->toBeTrue()
        ->and($respuesta->json('data.conflicto_ficha.codigo'))->toBe('VINCULO_SUPERPUESTO')
        ->and($respuesta->json('data.conflicto_ficha.desde'))->toBe(hoyDelTenant(-10))
        ->and($posicion->id)->toBeInt();
});

it('valida la fecha y responde 404 para una ficha ajena', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '37']);
    $ajena = fichaDe(['numero_identificacion' => '38'], 2);

    $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso")->assertUnprocessable()->assertJsonValidationErrors(['fecha']);
    $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=15-10-2026")->assertUnprocessable();
    $this->getJson("/api/colaboradores/{$ajena->id}/opciones-ingreso?fecha=".hoyDelTenant())->assertNotFound();
});

it('con Estructura caída responde 503 y no una lista vacía engañosa', function () {
    $ficha = fichaDe(['numero_identificacion' => '39']);
    app()->bind(
        PosicionesAsignablesPort::class,
        fn () => new class implements PosicionesAsignablesPort
        {
            public function listar(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
            {
                throw new IngresoNoVerificable;
            }

            public function verificar(int $posicionId, int $tenantId): array
            {
                throw new IngresoNoVerificable;
            }

            public function describir(int $posicionId): ?array
            {
                throw new IngresoNoVerificable;
            }
        },
    );
    actuarComoAdminDeTenant();

    $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant())
        ->assertStatus(503)->assertJsonPath('code', 'ESTRUCTURA_NO_VERIFICABLE');
});
