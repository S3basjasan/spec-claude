<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

/** @return array{id: int, ficha: ColaboradorModel, vinculo: int, tareas: list<array<string, mixed>>} */
function salidaParaAplicar(mixed $prueba, int $dias = 3, array $cambios = []): array
{
    politicaVigente('Offboarding');

    return salidaFormalizada($prueba, array_replace(['fecha_efectiva' => hoyDelTenant($dias)], $cambios));
}

it('con fecha efectiva futura el vínculo sigue vigente y la aplicación manual se rechaza (CA-07)', function () {
    $salida = salidaParaAplicar($this);

    accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => 1])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');
    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 0')->assertSuccessful();

    expect(DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->value('fecha_salida'))->toBeNull()
        ->and(DB::table('asignaciones_laborales')->where('vinculo_id', $salida['vinculo'])->whereNull('fecha_fin')->count())->toBe(1)
        ->and(DB::table('offboarding_expedientes')->value('estado'))->toBe('Programada');
});

it('llegada la fecha, el sistema cierra la asignación y el vínculo, libera la posición y conserva la ficha (CA-06)', function () {
    $salida = salidaParaAplicar($this);
    $fecha = hoyDelTenant(3);
    $posicion = DB::table('asignaciones_laborales')->where('vinculo_id', $salida['vinculo'])->value('posicion_id');

    Carbon::setTestNow(now()->addDays(4));
    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 1')->assertSuccessful();

    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();
    expect(DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->value('fecha_salida'))->toBe($fecha)
        ->and(DB::table('asignaciones_laborales')->where('vinculo_id', $salida['vinculo'])->value('fecha_fin'))->toBe($fecha)
        ->and($detalle->json('data.estado'))->toBe('Efectiva')
        ->and($detalle->json('data.aplicacion.por_sistema'))->toBeTrue()
        ->and($detalle->json('data.aplicacion.aplicada_en'))->not->toBeNull()
        ->and($detalle->json('data.aplicacion.intentos'))->toBe(1)
        ->and($detalle->json('data.acciones.cancelar'))->toBeFalse()
        ->and(ColaboradorModel::query()->whereKey($salida['ficha']->id)->exists())->toBeTrue()
        ->and(DB::table('vinculos_laborales')->where('colaborador_id', $salida['ficha']->id)->count())->toBe(1);

    // La posición queda libre desde la fecha efectiva: otra persona puede ocuparla ese día.
    $reasignada = vinculoDe(fichaDe(['numero_identificacion' => 'R'.uniqid()])->id, $fecha, null, (int) $posicion);
    expect($reasignada)->toBeGreaterThan(0);
});

it('aplicar es idempotente: repetir el proceso no duplica el hecho ni el evento (CA-09)', function () {
    $salida = salidaParaAplicar($this);
    Carbon::setTestNow(now()->addDays(4));

    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();
    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 0')->assertSuccessful();

    expect(DB::table('offboarding_eventos')->where('expediente_id', $salida['id'])->where('evento', 'Aplicada')->count())->toBe(1)
        ->and(DB::table('offboarding_expedientes')->value('intentos_aplicacion'))->toBe(1);
});

it('el Administrador aplica una salida cuya fecha llegó con control de versión y clave idempotente (HU-04)', function () {
    $salida = salidaParaAplicar($this, 1);
    Carbon::setTestNow(now()->addDays(2));
    $clave = (string) Str::uuid();

    accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => 5])->assertStatus(409)->assertJsonPath('code', 'EXPEDIENTE_MODIFICADO');
    $aplicada = accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => 1], $clave)->assertOk();
    $repetida = accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => 1], $clave)->assertOk();

    expect($aplicada->json('data.estado'))->toBe('Efectiva')
        ->and($aplicada->json('data.aplicacion.por_sistema'))->toBeFalse()
        ->and($repetida->json('data.version'))->toBe($aplicada->json('data.version'))
        ->and(DB::table('offboarding_eventos')->where('evento', 'Aplicada')->count())->toBe(1);

    accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => $aplicada->json('data.version')])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');
});

it('la comparación de fechas usa la zona horaria del tenant, no la del servidor (CA-06)', function () {
    tenantConZona('Pacific/Kiritimati');
    politicaVigente('Offboarding');
    // Días relativos a hoy para que la política publicada (vigente desde ayer) siga en vigor.
    $base = Carbon::now('UTC')->addDays(5)->startOfDay();
    Carbon::setTestNow($base->copy()->addHours(12));
    $ficha = fichaConVinculo(['numero_identificacion' => 'TZ'.uniqid()], 1, $base->copy()->subDays(300)->toDateString())['ficha'];
    $salida = salidaFormalizada($this, ['fecha_efectiva' => $base->copy()->addDays(2)->toDateString()], $ficha);

    // 19:00 del día previo a la fecha efectiva en Kiritimati: aún no es la fecha efectiva.
    Carbon::setTestNow($base->copy()->addDay()->addHours(5));
    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 0')->assertSuccessful();
    expect(DB::table('offboarding_expedientes')->value('estado'))->toBe('Programada');

    // 02:00 de la fecha efectiva en Kiritimati, todavía el día previo en UTC y en Guayaquil: ya es la fecha del tenant.
    Carbon::setTestNow($base->copy()->addDay()->addHours(12));
    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 1')->assertSuccessful();
    expect(DB::table('offboarding_expedientes')->where('id', $salida['id'])->value('estado'))->toBe('Efectiva');
});

it('el proceso no mezcla tenants: aplica en cada uno según su fecha', function () {
    politicaVigente('Offboarding');
    politicaVigente('Offboarding', [], 2);
    $uno = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(1)]);
    $dos = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(8)], null, 2);
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 1')->assertSuccessful();

    expect(DB::table('offboarding_expedientes')->where('id', $uno['id'])->value('estado'))->toBe('Efectiva')
        ->and(DB::table('offboarding_expedientes')->where('id', $dos['id'])->value('estado'))->toBe('Programada');
});

it('la cuenta queda «Pendiente» sin contrato de Identidad y Acceso: nunca se afirma deshabilitada (RN cuenta)', function () {
    $salida = salidaParaAplicar($this, 1);
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();

    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('Efectiva')
        ->and($detalle->json('data.cuenta_estado'))->toBe('Pendiente')
        ->and(DB::table('offboarding_eventos')->where('evento', 'CuentaCoordinada')->count())->toBe(0);
});

it('solo cuando el componente propietario lo confirma, la cuenta figura como coordinada', function () {
    coordinadorDeCuenta('coordinada');
    $salida = salidaParaAplicar($this, 1);
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();

    expect($this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.cuenta_estado'))->toBe('Coordinada')
        ->and(DB::table('offboarding_eventos')->where('evento', 'CuentaCoordinada')->count())->toBe(1);
});

it('un fallo al coordinar la cuenta no revierte la salida y se registra como incidencia de la cuenta', function () {
    coordinadorDeCuenta('falla');
    $salida = salidaParaAplicar($this, 1);
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();

    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('Efectiva')
        ->and($detalle->json('data.cuenta_estado'))->toBe('Incidencia')
        ->and(DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->value('fecha_salida'))->not->toBeNull();
});

it('si el vínculo ya no es vigente queda una incidencia sin cambios parciales y se puede reintentar (RN incidencia)', function () {
    $salida = salidaParaAplicar($this, 3);
    // Un cambio externo terminó el vínculo antes de la fecha efectiva.
    DB::table('asignaciones_laborales')->where('vinculo_id', $salida['vinculo'])->update(['fecha_fin' => hoyDelTenant(1)]);
    DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->update(['fecha_salida' => hoyDelTenant(1)]);
    Carbon::setTestNow(now()->addDays(4));

    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('incidencias: 1')->assertSuccessful();

    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('IncidenciaDeAplicacion')
        ->and($detalle->json('data.aplicacion.incidencia.codigo'))->toBe('VINCULO_NO_VIGENTE')
        ->and($detalle->json('data.acciones.aplicar'))->toBeTrue()
        ->and($detalle->json('data.acciones.cancelar'))->toBeTrue();

    // El proceso no reintenta incidencias; el Administrador sí, y vuelve a comunicar la incidencia sin éxito falso.
    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 0')->assertSuccessful();
    $reintento = accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => $detalle->json('data.version')])->assertStatus(409);
    expect($reintento->json('code'))->toBe('INCIDENCIA_DE_APLICACION')
        ->and($reintento->json('incidencia.codigo'))->toBe('VINCULO_NO_VIGENTE')
        ->and(DB::table('offboarding_eventos')->where('expediente_id', $salida['id'])->whereIn('evento', ['IncidenciaDeAplicacion', 'ReintentoDeAplicacion'])->pluck('evento')->all())->toBe(['IncidenciaDeAplicacion', 'ReintentoDeAplicacion'])
        ->and(DB::table('offboarding_expedientes')->value('intentos_aplicacion'))->toBe(2);

    // Una salida con incidencia puede cancelarse (no se fuerza ninguna asignación).
    $version = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.version');
    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => $version, 'razon' => 'El vínculo ya terminó por otra vía'])->assertOk()->assertJsonPath('data.estado', 'Cancelada');
});

it('una asignación posterior a la fecha efectiva genera una incidencia y deja todo como estaba', function () {
    $salida = salidaParaAplicar($this, 3);
    $antes = [DB::table('vinculos_laborales')->get()->toArray(), DB::table('asignaciones_laborales')->get()->toArray()];
    // La asignación abierta empieza después de la fecha efectiva (cambio externo no compatible).
    DB::table('asignaciones_laborales')->where('vinculo_id', $salida['vinculo'])->update(['fecha_inicio' => hoyDelTenant(5)]);
    $antes[1] = DB::table('asignaciones_laborales')->get()->toArray();
    Carbon::setTestNow(now()->addDays(4));

    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('incidencias: 1')->assertSuccessful();

    expect($this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.aplicacion.incidencia.codigo'))->toBe('ASIGNACION_CAMBIO')
        ->and([DB::table('vinculos_laborales')->get()->toArray(), DB::table('asignaciones_laborales')->get()->toArray()])->toEqual($antes);
});

it('un expediente cancelado nunca se aplica y una salida efectiva no se reaplica ni se cancela', function () {
    $salida = salidaParaAplicar($this, 1);
    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Cancelada'])->assertOk();
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('offboarding:aplicar-salidas')->expectsOutputToContain('Aplicadas: 0')->assertSuccessful();
    accionSalidaApi($this, $salida['id'], 'aplicacion', ['version' => 2])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');
    expect(DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->value('fecha_salida'))->toBeNull();

    $otra = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(1)]);
    Carbon::setTestNow(now()->addDays(2));
    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();
    accionSalidaApi($this, $otra['id'], 'cancelacion', ['version' => 2, 'razon' => 'Tarde'])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
});

it('la salida efectiva aparece en la trayectoria laboral con su motivo y sin sobrescribir el ingreso (CA-09)', function () {
    $salida = salidaParaAplicar($this, 1, ['motivo' => 'Jubilacion']);
    $fechaDeSalida = hoyDelTenant(1);
    $fechaDeIngreso = hoyDelTenant(-200);
    Carbon::setTestNow(now()->addDays(2));
    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();

    $trayectoria = $this->getJson("/api/colaboradores/{$salida['ficha']->id}/trayectoria")->assertOk()->json('data');

    $tipos = collect($trayectoria)->pluck('tipo')->all();
    expect($tipos)->toEqualCanonicalizing(['Ingreso', 'Salida'])
        ->and(collect($trayectoria)->firstWhere('tipo', 'Salida')['fecha_efectiva'])->toBe($fechaDeSalida)
        ->and(collect($trayectoria)->firstWhere('tipo', 'Ingreso')['fecha_efectiva'])->toBe($fechaDeIngreso);
});
