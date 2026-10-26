<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Movimiento aprobado con fecha efectiva hoy: listo para aplicarse. */
function movimientoListoParaAplicar(mixed $prueba): array
{
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();

    return movimientoAprobado($prueba, ['fecha_efectiva' => hoyDelTenant(0)]);
}

function sinCambiosDeAsignacion(array $movimiento, array $antes): void
{
    expect(DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray())->toEqual($antes)
        ->and(DB::table('asignaciones_laborales')->where('posicion_id', $movimiento['origen']->id)->value('fecha_fin'))->toBeNull()
        ->and(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(0)
        ->and(DB::table('movimientos_personal')->value('asignacion_nueva_id'))->toBeNull();
}

it('un destino ocupado deja una incidencia sin asignación parcial y no se comunica como aplicado (US5.3, RMN-CA04, RMN-CA13)', function () {
    $movimiento = movimientoListoParaAplicar($this);
    ['ficha' => $otra] = fichaConVinculo([], 1, hoyDelTenant(-5), $movimiento['destino']);
    $antes = DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray();

    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('incidencias: 1')->assertSuccessful();

    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('IncidenciaDeAplicacion')
        ->and($detalle->json('data.movimiento.aplicacion.estado'))->toBe('Incidencia')
        ->and($detalle->json('data.movimiento.aplicacion.incidencia.codigo'))->toBe('DESTINO_OCUPADO')
        ->and($detalle->json('data.movimiento.aplicacion.aplicada_en'))->toBeNull()
        ->and($detalle->json('data.acciones.aplicar'))->toBeTrue()
        ->and(collect($detalle->json('data.historial'))->last()['evento'])->toBe('IncidenciaDeAplicacion')
        ->and($otra->id)->toBeInt();
    sinCambiosDeAsignacion($movimiento, $antes);

    actuarComoColaboradorDe($movimiento['ficha']);
    $mia = $this->getJson(URL_MIS_SOLICITUDES.'/'.$movimiento['id'])->assertOk()->json('data');
    expect($mia['estado'])->toBe('Aprobada')->and(json_encode($mia))->not->toContain('ncidencia')->and(json_encode($mia))->not->toContain('DESTINO');
});

it('el reintento manual conserva la incidencia mientras siga sin cumplirse y aplica una sola vez cuando ya se cumple (US5.4)', function () {
    $movimiento = movimientoListoParaAplicar($this);
    fichaConVinculo([], 1, hoyDelTenant(-5), $movimiento['destino']);
    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();
    $version = DB::table('actuaciones_personal')->value('version');
    $antes = DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray();

    $fallido = accionApi($this, $movimiento['id'], 'aplicacion', ['version' => $version])
        ->assertStatus(409)->assertJsonPath('code', 'INCIDENCIA_DE_APLICACION')->assertJsonPath('incidencia.codigo', 'DESTINO_OCUPADO');
    expect($fallido->json('message'))->toBeString();
    sinCambiosDeAsignacion($movimiento, $antes);
    expect(DB::table('movimientos_personal')->value('intentos_aplicacion'))->toBe(2)
        ->and(DB::table('actuacion_transiciones')->where('evento', 'ReintentoDeAplicacion')->count())->toBe(1);

    // La posición se libera y el reintento aplica exactamente una vez.
    DB::table('asignaciones_laborales')->where('posicion_id', $movimiento['destino']->id)->update(['fecha_fin' => hoyDelTenant(-5)]);
    $clave = (string) Str::uuid();
    $version = DB::table('actuaciones_personal')->value('version');
    $aplicada = accionApi($this, $movimiento['id'], 'aplicacion', ['version' => $version], $clave)->assertOk();
    expect($aplicada->json('data.estado'))->toBe('Aplicada')
        ->and($aplicada->json('data.movimiento.aplicacion.incidencia'))->toBeNull();

    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => $version], $clave)->assertOk();
    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => $version + 1])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');
    expect(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(1);
});

it('el proceso automático no reintenta una incidencia por su cuenta (clarificación 1)', function () {
    $movimiento = movimientoListoParaAplicar($this);
    fichaConVinculo([], 1, hoyDelTenant(-5), $movimiento['destino']);
    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();
    DB::table('asignaciones_laborales')->where('posicion_id', $movimiento['destino']->id)->update(['fecha_fin' => hoyDelTenant(-5)]);

    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 0')->assertSuccessful();

    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('IncidenciaDeAplicacion')
        ->and(DB::table('movimientos_personal')->value('intentos_aplicacion'))->toBe(1);
});

it('cada condición actual que cambia produce su propia incidencia sin cambios parciales', function (string $codigo, Closure $romper) {
    $movimiento = movimientoListoParaAplicar($this);
    $romper($movimiento);
    $antes = DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray();

    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();

    expect(DB::table('movimientos_personal')->value('incidencia_codigo'))->toBe($codigo)
        ->and(DB::table('actuaciones_personal')->value('estado'))->toBe('IncidenciaDeAplicacion')
        ->and(DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray())->toEqual($antes)
        ->and(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(0);
})->with([
    'destino no asignable' => ['DESTINO_NO_ASIGNABLE', fn (array $m) => PosicionModel::query()->whereKey($m['destino']->id)->update(['estado' => 'Suprimida'])],
    'vínculo terminado' => ['VINCULO_NO_VIGENTE', function (array $m) {
        // GP-02 no admite acortar un vínculo sobre una asignación abierta: primero se cierra la asignación, como lo haría una salida.
        DB::table('asignaciones_laborales')->where('posicion_id', $m['origen']->id)->update(['fecha_fin' => hoyDelTenant(-1)]);
        DB::table('vinculos_laborales')->where('id', $m['vinculo'])->update(['fecha_salida' => hoyDelTenant(-1)]);
    }],
    'origen cambiado' => ['ORIGEN_CAMBIO', fn (array $m) => DB::table('asignaciones_laborales')->where('posicion_id', $m['origen']->id)->update(['fecha_fin' => hoyDelTenant(-1)])],
    'Estructura no verificable' => ['ESTRUCTURA_NO_VERIFICABLE', function (array $m) {
        app()->bind(PosicionesAsignablesPort::class, fn () => new class implements PosicionesAsignablesPort
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
        });
    }],
]);

it('un fallo técnico al aplicar revierte todo, deja el movimiento pendiente y se recupera sin duplicar (RMN-CA13)', function () {
    $movimiento = movimientoListoParaAplicar($this);
    $antes = DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray();
    DB::unprepared('CREATE TRIGGER fallo_de_prueba BEFORE INSERT ON asignaciones_laborales WHEN NEW.actuacion_id IS NOT NULL BEGIN SELECT RAISE(ABORT, \'FALLO_DE_PRUEBA\'); END');

    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('omitidos: 1')->assertSuccessful();

    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Aprobada')
        ->and(DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray())->toEqual($antes);
    sinCambiosDeAsignacion($movimiento, $antes);

    DB::unprepared('DROP TRIGGER fallo_de_prueba');
    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 1')->assertSuccessful();
    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Aplicada')
        ->and(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(1);
});

it('un reintento con una versión obsoleta se rechaza sin tocar nada', function () {
    $movimiento = movimientoListoParaAplicar($this);
    fichaConVinculo([], 1, hoyDelTenant(-5), $movimiento['destino']);
    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();

    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 1])->assertStatus(409)->assertJsonPath('code', 'SOLICITUD_MODIFICADA');
});
