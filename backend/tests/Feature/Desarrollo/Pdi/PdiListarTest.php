<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

function pdiPlanesDeEjemplo(int $adminId): array
{
    return [
        'a' => pdiEnBd(['colaborador' => 10, 'responsable' => $adminId, 'fin' => '2026-12-31']),
        'b' => pdiEnBd(['colaborador' => 11, 'tipo' => TipoDePdi::Carrera, 'estado' => EstadoDePdi::Acordado, 'responsable' => 7, 'fin' => '2026-09-30', 'hitos' => [['2026-06-01'], ['2026-09-01']]]),
        'c' => pdiEnBd(['colaborador' => 12, 'tipo' => TipoDePdi::Talento, 'estado' => EstadoDePdi::Cerrado, 'responsable' => $adminId, 'fin' => '2026-08-15', 'hitos' => [['2026-06-01']]]),
        'd' => pdiEnBd(['colaborador' => 13, 'estado' => EstadoDePdi::Acordado, 'responsable' => $adminId, 'fin' => '2027-01-31', 'acciones' => ['Cumplida', 'Pendiente']]),
    ];
}

it('lista los planes del más reciente al más antiguo con los datos de cada fila', function () {
    $admin = pdiComoAdmin();
    $planes = pdiPlanesDeEjemplo((int) $admin->id);

    $r = $this->getJson('/api/pdi')->assertOk();

    expect(collect($r->json('data'))->pluck('id')->all())->toBe([$planes['d']->id, $planes['c']->id, $planes['b']->id, $planes['a']->id])
        ->and($r->json('meta.total'))->toBe(4)
        ->and($r->json('data.0'))->toHaveKeys(['codigo', 'colaborador', 'tipo', 'estado', 'avance', 'responsable', 'fecha_fin', 'hitos_vencidos'])
        ->and($r->json('data.0.colaborador.nombre'))->toBe('Colaborador 13')
        ->and($r->json('data.0.colaborador.departamento'))->not->toBeNull()
        ->and($r->json('data.0.avance'))->toBe(50)
        ->and($r->json('data.2.hitos_vencidos'))->toBe(1)
        ->and($r->json('data.1.hitos_vencidos'))->toBe(0)
        ->and($r->json('data.2.puesto_objetivo.id'))->toBe(2);
});

it('pagina la lista', function () {
    $admin = pdiComoAdmin();
    pdiPlanesDeEjemplo((int) $admin->id);

    $r = $this->getJson('/api/pdi?per_page=3&page=2')->assertOk();

    expect($r->json('data'))->toHaveCount(1)->and($r->json('meta.last_page'))->toBe(2)->and($r->json('meta.page'))->toBe(2);
    $this->getJson('/api/pdi?per_page=99')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('filtra por tipo, estado, colaborador, responsable, departamento y rango de fechas, solos y combinados', function () {
    $admin = pdiComoAdmin();
    $planes = pdiPlanesDeEjemplo((int) $admin->id);
    $ids = fn (string $query) => collect($this->getJson('/api/pdi?'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    $de = fn (string ...$claves) => collect($claves)->map(fn ($k) => $planes[$k]->id)->sort()->values()->all();

    expect($ids('tipo=Carrera'))->toBe($de('b'))
        ->and($ids('estado=Acordado'))->toBe($de('b', 'd'))
        ->and($ids('colaborador_id=12'))->toBe($de('c'))
        ->and($ids('responsable_id=7'))->toBe($de('b'))
        ->and($ids('departamento_id=1'))->toBe($de('a', 'b'))
        ->and($ids('departamento_id=2'))->toBe($de('c', 'd'))
        ->and($ids('fecha_desde=2026-09-01'))->toBe($de('a', 'b', 'd'))
        ->and($ids('fecha_hasta=2026-09-30'))->toBe($de('b', 'c'))
        ->and($ids('fecha_desde=2026-09-01&fecha_hasta=2026-12-31'))->toBe($de('a', 'b'))
        ->and($ids('estado=Acordado&departamento_id=2'))->toBe($de('d'))
        ->and($ids('tipo=Talento&estado=Borrador'))->toBe([]);
    $this->getJson('/api/pdi?fecha_desde=2026-10-01&fecha_hasta=2026-09-01')->assertUnprocessable()->assertJsonValidationErrors('fecha_hasta');
    $this->getJson('/api/pdi?tipo=Otro')->assertUnprocessable();
});

it('calcula los indicadores del conjunto', function () {
    $admin = pdiComoAdmin();
    pdiPlanesDeEjemplo((int) $admin->id);

    $this->getJson('/api/pdi?estado=Cerrado')->assertOk()->assertJsonPath('meta.kpis', ['borrador' => 1, 'acordado' => 2, 'cerrado' => 1, 'hitos_vencidos' => 1]);
});

it('solo el administrador lista y se aísla por tenant', function () {
    $admin = pdiComoAdmin();
    tenantDos();
    pdiEnBd(['tenant' => 2, 'colaborador' => 10]);
    pdiEnBd(['colaborador' => 10, 'responsable' => (int) $admin->id]);

    $this->getJson('/api/pdi')->assertOk()->assertJsonPath('meta.total', 1);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/pdi')->assertForbidden();
});
