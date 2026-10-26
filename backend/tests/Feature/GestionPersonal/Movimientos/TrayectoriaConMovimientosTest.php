<?php

use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use Illuminate\Support\Facades\DB;

it('el movimiento aplicado alimenta la trayectoria con referencia a la solicitud y a la política, sin alterar el ingreso (FR-043)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    $rechazado = movimientoPresentado($this, [], $movimiento['ficha']);

    $antes = $this->getJson('/api/colaboradores/'.$movimiento['ficha']->id.'/trayectoria')->assertOk()->json('data');
    expect(collect($antes)->pluck('tipo')->all())->toBe(['Ingreso']);

    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 2])->assertOk();

    $hechos = $this->getJson('/api/colaboradores/'.$movimiento['ficha']->id.'/trayectoria')->assertOk()->json('data');
    $ascenso = collect($hechos)->firstWhere('tipo', 'Ascenso');
    $ingreso = collect($hechos)->firstWhere('tipo', 'Ingreso');

    expect($hechos)->toHaveCount(2)
        ->and($ascenso['actuacion_id'])->toBe($movimiento['id'])
        ->and($ascenso['politica_numero'])->toBe(1)
        ->and($ascenso['fecha_efectiva'])->toBe(hoyDelTenant(0))
        ->and($ascenso['posicion']['id'])->toBe($movimiento['destino']->id)
        ->and($ascenso['posicion']['puesto'])->toBeString()
        ->and($ascenso['programado'])->toBeFalse()
        ->and($ingreso['fecha_efectiva'])->toBe(DB::table('vinculos_laborales')->value('fecha_ingreso'))
        ->and($ingreso['posicion']['id'])->toBe($movimiento['origen']->id === $ingreso['posicion']['id'] ? $movimiento['origen']->id : $ingreso['posicion']['id']);

    // Una propuesta que no se aplicó no es un hecho laboral.
    expect(collect($hechos)->pluck('actuacion_id')->filter()->all())->not->toContain($rechazado['id']);
});

it('el lector laboral publica el mismo hecho para el resto de módulos y mantiene la forma de los ingresos', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 2])->assertOk();

    $lectura = app(LectorLaboralPort::class)->trayectoria(1, $movimiento['ficha']->id);
    $hechos = collect($lectura->datos);

    expect($lectura->estaDisponible())->toBeTrue()
        ->and($hechos->pluck('tipo')->all())->toBe(['Ingreso', 'Ascenso'])
        ->and($hechos->every(fn (array $h) => array_key_exists('tipo_contrato', $h) && array_key_exists('vinculo_id', $h) && array_key_exists('posicion_id', $h)))->toBeTrue()
        ->and($hechos->last()['actuacion_id'])->toBe($movimiento['id']);
});
