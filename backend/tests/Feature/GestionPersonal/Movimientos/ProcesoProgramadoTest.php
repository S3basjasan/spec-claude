<?php

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\UseCases\AplicarMovimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(fn () => Carbon::setTestNow());

it('aplica por la zona horaria de cada tenant y no cruza datos entre tenants (US5.2, aislamiento programado)', function () {
    // 15:00 UTC: en Guayaquil (UTC-5) es el 22 a las 10:00; en Kiritimati (UTC+14) ya es el 23 a las 05:00.
    Carbon::setTestNow('2026-10-22 15:00:00 UTC');
    tenantDos();
    tenantConZona('Pacific/Kiritimati', 2);
    // Las políticas rigen desde antes de las fechas fijas de la prueba, sea cual sea la fecha real de ejecución.
    foreach ([1, 2] as $tenant) {
        politicaPublicada('Ascensos', ['tenant_id' => $tenant, 'inicio' => new DateTimeImmutable('2026-01-01 00:00:00 UTC'), 'parametros' => array_merge(parametrosValidos('Ascensos'), ['anticipacion_dias' => 0])]);
    }

    actuarComoAdminDeTenant(1);
    $uno = movimientoAprobado($this, ['fecha_efectiva' => '2026-10-24']);
    actuarComoAdminDeTenant(2);
    $dos = movimientoAprobado($this, ['fecha_efectiva' => '2026-10-24'], null, null, 2);
    expect(DB::table('actuaciones_personal')->whereIn('id', [$uno['id'], $dos['id']])->pluck('estado')->all())->toBe(['Programada', 'Programada']);

    // 15:00 UTC del 23: Guayaquil sigue en el 23 (no vence el 24) y Kiritimati ya está en el 24 (vence).
    Carbon::setTestNow('2026-10-23 15:00:00 UTC');
    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 1')->assertSuccessful();

    expect(DB::table('actuaciones_personal')->where('id', $dos['id'])->value('estado'))->toBe('Aplicada')
        ->and(DB::table('actuaciones_personal')->where('id', $uno['id'])->value('estado'))->toBe('Programada')
        ->and(DB::table('asignaciones_laborales')->where('tenant_id', 2)->whereNotNull('actuacion_id')->value('posicion_id'))->toBe($dos['destino']->id)
        ->and(DB::table('asignaciones_laborales')->where('tenant_id', 1)->whereNotNull('actuacion_id')->count())->toBe(0)
        ->and(DB::table('asignaciones_laborales')->where('posicion_id', $dos['origen']->id)->value('fecha_fin'))->toBe('2026-10-24');

    // Cuando le toca a Guayaquil se aplica el suyo, sin tocar el del otro.
    Carbon::setTestNow('2026-10-24 15:00:00 UTC');
    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 1')->assertSuccessful();
    expect(DB::table('actuaciones_personal')->where('id', $uno['id'])->value('estado'))->toBe('Aplicada')
        ->and(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(2);
});

it('es idempotente: una segunda ejecución no vuelve a aplicar ni duplica el hecho (RMN-CA12)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);

    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 1')->assertSuccessful();
    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 0')->assertSuccessful();

    expect(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(1)
        ->and(DB::table('actuacion_transiciones')->where('evento', 'Aplicada')->count())->toBe(1);

    // La aplicación automática frente a un reintento manual simultáneo: la segunda no aplica nada.
    $contexto = new ContextoDeTenant(1, 'Prueba', 'America/Guayaquil');
    $segunda = app(AplicarMovimiento::class)->comoSistema($contexto, $movimiento['id']);
    expect($segunda->nueva)->toBeFalse()
        ->and(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(1);
});

it('un retraso del proceso deja el movimiento programado y no aplicado, y al ejecutarse verifica las condiciones del momento (FR-023b)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this);

    // Pasan varios días sin ejecutar el proceso: sigue programado, no aplicado.
    Carbon::setTestNow(now()->addDays(34));
    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('Programada')->and($detalle->json('data.acciones.aplicar'))->toBeTrue();

    // Entre tanto el destino se ocupó: al ejecutarse queda una incidencia, no un cambio.
    fichaConVinculo([], 1, hoyDelTenant(-1), $movimiento['destino']);
    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();

    expect(DB::table('movimientos_personal')->value('incidencia_codigo'))->toBe('DESTINO_OCUPADO')
        ->and(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(0);
});

it('un proceso sin movimientos pendientes termina sin error ni efectos', function () {
    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 0 · incidencias: 0 · omitidos: 0')->assertSuccessful();
});
