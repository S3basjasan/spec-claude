<?php

use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;

it('lista el historial con cantidad de brechas y prioridad más alta, sin detalle', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', 'B']]]);
    dnfDeEjemplo([10 => [[1, 'A', 'D']], 11 => [[1, 'B', 'C']]], 'Departamento', 5);

    $respuesta = $this->getJson('/api/dnf')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(2)
        ->and($respuesta->json('data.0'))->toBe([
            'id' => $respuesta->json('data.0.id'),
            'codigo' => 'DNF-0002',
            'fecha' => $respuesta->json('data.0.fecha'),
            'alcance' => 'Departamento',
            'colaborador' => null,
            'departamento' => ['id' => 5, 'nombre' => 'Unidad 5'],
            'cantidad_brechas' => 2,
            'prioridad_mas_alta' => 'Critica',
            'tiene_plan' => false,
        ])
        ->and($respuesta->json('data.1.colaborador'))->toBe(['id' => 10, 'nombre' => 'Colaborador 10'])
        ->and($respuesta->json('data.1.prioridad_mas_alta'))->toBe('Alta')
        ->and($respuesta->json('data.1'))->not->toHaveKey('colaboradores')
        ->and($respuesta->json('meta'))->toBe(['page' => 1, 'per_page' => 15, 'total' => 2, 'last_page' => 1]);
});

it('ordena por fecha de generación descendente', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $antigua = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    DnfModel::query()->whereKey($antigua->id)->toBase()->update(['created_at' => '2026-01-01 10:00:00']);
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $codigos = array_column($this->getJson('/api/dnf')->json('data'), 'codigo');

    expect($codigos)->toBe(['DNF-0002', 'DNF-0001']);
});

it('filtra por rango de fechas, colaborador, departamento y prioridad más alta', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $antigua = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    DnfModel::query()->whereKey($antigua->id)->toBase()->update(['created_at' => '2026-01-10 10:00:00']);
    dnfDeEjemplo([10 => [[1, 'A', 'D']], 11 => [[1, 'B', 'C']]], 'Departamento', 5);
    dnfDeEjemplo([12 => [[1, 'A', 'B']]]);

    $total = fn (string $consulta) => $this->getJson('/api/dnf?'.$consulta)->assertOk()->json('meta.total');

    expect($total('fecha_hasta=2026-02-01'))->toBe(1)
        ->and($total('fecha_desde=2026-02-01'))->toBe(2)
        ->and($total('fecha_desde=2026-01-01&fecha_hasta=2026-01-10'))->toBe(1)
        ->and($total('colaborador_id=11'))->toBe(1)
        ->and($total('colaborador_id=10'))->toBe(2)
        ->and($total('departamento_id=5'))->toBe(1)
        ->and($total('prioridad=Critica'))->toBe(1)
        ->and($total('prioridad=Baja'))->toBe(0);
});

it('pagina con page y per_page', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    foreach (range(1, 5) as $i) {
        dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    }

    $respuesta = $this->getJson('/api/dnf?page=2&per_page=2')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(2)
        ->and($respuesta->json('meta'))->toBe(['page' => 2, 'per_page' => 2, 'total' => 5, 'last_page' => 3])
        ->and($respuesta->json('data.0.codigo'))->toBe('DNF-0003');
});

it('valida los filtros', function (string $consulta, string $campo) {
    dnfFalsos();
    actuarComoAdminDeTenant();

    $this->getJson('/api/dnf?'.$consulta)->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    'fecha inicial posterior a la final' => ['fecha_desde=2026-10-10&fecha_hasta=2026-10-01', 'fecha_hasta'],
    'formato de fecha inválido' => ['fecha_desde=10/10/2026', 'fecha_desde'],
    'prioridad desconocida' => ['prioridad=Urgente', 'prioridad'],
    'colaborador no numérico' => ['colaborador_id=abc', 'colaborador_id'],
    'per_page fuera de rango' => ['per_page=101', 'per_page'],
    'page cero' => ['page=0', 'page'],
]);

it('aísla el historial por tenant', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    tenantDos();
    dnfDeEjemplo([10 => [[1, 'A', 'C']]], tenantId: 2);
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->getJson('/api/dnf')->assertOk()->assertJsonPath('meta.total', 1);
});

it('indica si la DNF ya generó un plan', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    app(DnfRepositoryInterface::class)->asignarPlan(1, $dnf->id, 31, 1, new DateTimeImmutable);

    $this->getJson('/api/dnf')->assertOk()->assertJsonPath('data.0.tiene_plan', true);
});

it('exige autenticación y rol de administrador', function () {
    dnfFalsos();

    $this->getJson('/api/dnf')->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/dnf')->assertForbidden();
});
