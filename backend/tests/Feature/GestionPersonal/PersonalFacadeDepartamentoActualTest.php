<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Posición vacante de la unidad, bajo una posición raíz del tenant (solo una posición puede no tener jefe). */
function posicionDeDepartamento(int $unidadId, int $tenantId = 1): PosicionModel
{
    $raiz = PosicionModel::query()->where('tenant_id', $tenantId)->whereNull('posicion_jefe_id')->first() ?? posicionAsignable([], $tenantId);

    return posicionAsignable(['unidad_organizacional_id' => $unidadId, 'posicion_jefe_id' => $raiz->id], $tenantId);
}

beforeEach(function () {
    $this->unidadA = unidadDe();
    $this->unidadB = unidadDe();
});

it('devuelve la unidad de la posición de la asignación vigente', function () {
    $ficha = fichaDe(['numero_identificacion' => '1100']);
    $posicion = posicionDeDepartamento($this->unidadA->id);
    vinculoDe($ficha->id, '2025-01-01', null, $posicion->id);

    expect(PersonalFacade::getDepartamentoActual(1, [$ficha->id], '2026-03-05'))->toBe([$ficha->id => $this->unidadA->id]);
});

it('devuelve nulo para quien no tiene asignación vigente en la fecha', function () {
    $sinAsignacion = fichaDe(['numero_identificacion' => '1101']);
    $conSalida = fichaDe(['numero_identificacion' => '1102']);
    $futura = fichaDe(['numero_identificacion' => '1103']);
    $posicion = fn () => posicionDeDepartamento($this->unidadA->id)->id;
    vinculoDe($conSalida->id, '2024-01-01', '2025-12-31', $posicion());
    vinculoDe($futura->id, '2026-06-01', null, $posicion());

    $r = PersonalFacade::getDepartamentoActual(1, [$sinAsignacion->id, $conSalida->id, $futura->id], '2026-03-05');

    expect($r)->toBe([$sinAsignacion->id => null, $conSalida->id => null, $futura->id => null]);
});

it('refleja el cambio de departamento: usa la asignación vigente, no la histórica', function () {
    $ficha = fichaDe(['numero_identificacion' => '1104']);
    $antes = posicionDeDepartamento($this->unidadA->id);
    $despues = posicionDeDepartamento($this->unidadB->id);
    vinculoDe($ficha->id, '2023-01-01', '2025-12-31', $antes->id);
    vinculoDe($ficha->id, '2026-01-01', null, $despues->id);

    expect(PersonalFacade::getDepartamentoActual(1, [$ficha->id], '2026-03-05'))->toBe([$ficha->id => $this->unidadB->id])
        ->and(PersonalFacade::getDepartamentoActual(1, [$ficha->id], '2025-06-01'))->toBe([$ficha->id => $this->unidadA->id]);
});

it('no incluye fichas de otro tenant', function () {
    tenantDos();
    $ajena = fichaDe(['numero_identificacion' => '1105'], 2);
    $posicion = posicionDeDepartamento($this->unidadA->id, 2);
    vinculoDe($ajena->id, '2025-01-01', null, $posicion->id, null, 2);

    expect(PersonalFacade::getDepartamentoActual(1, [$ajena->id], '2026-03-05'))->toBe([$ajena->id => null]);
});

it('responde vacío sin ids', function () {
    expect(PersonalFacade::getDepartamentoActual(1, [], '2026-03-05'))->toBe([]);
});

it('lanza InformacionNoDisponible si falla la lectura de Estructura', function () {
    $ficha = fichaDe(['numero_identificacion' => '1106']);
    $posicion = posicionDeDepartamento($this->unidadA->id);
    vinculoDe($ficha->id, '2025-01-01', null, $posicion->id);

    $lector = Mockery::mock(LectorEstructuraPort::class);
    $lector->shouldReceive('posiciones')->andReturn(Lectura::noDisponible());
    app()->instance(LectorEstructuraPort::class, $lector);
    app()->forgetInstance('personal-facade');

    expect(fn () => PersonalFacade::getDepartamentoActual(1, [$ficha->id], '2026-03-05'))->toThrow(InformacionNoDisponible::class);
});
