<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use Illuminate\Support\Facades\DB;

function asignacionesActuales(): array
{
    return DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray();
}

it('sin contrato de evaluación la aprobación se rechaza, informa la evidencia pendiente y conserva la asignación (US4.2, RMN-CA02)', function () {
    politicaVigente('Traslados', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this, ['tipo' => 'Traslado']);
    $antes = asignacionesActuales();

    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    expect($detalle->json('data.puede_aprobar'))->toBeFalse()
        ->and($detalle->json('data.motivo_no_aprobable'))->toBe('EVALUACION_NO_VERIFICABLE')
        ->and($detalle->json('data.acciones.aprobar'))->toBeFalse()
        ->and($detalle->json('data.movimiento.evaluacion.estado'))->toBe('NoDisponible')
        ->and(collect($detalle->json('data.comprobaciones'))->firstWhere('clave', 'evaluacion')['estado'])->toBe('NoVerificable');

    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Es apto'])
        ->assertStatus(422)->assertJsonPath('code', 'EVALUACION_NO_VERIFICABLE');

    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Presentada')
        ->and(asignacionesActuales())->toEqual($antes);
});

it('mientras no exista el contrato se puede presentar, consultar, pasar a revisión y rechazar (US4.4, FR-022b)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $uno = movimientoPresentado($this);
    $dos = movimientoPresentado($this);

    accionApi($this, $uno['id'], 'revision', ['version' => 1])->assertOk()->assertJsonPath('data.estado', 'EnRevision');
    accionApi($this, $uno['id'], 'rechazo', ['version' => 2, 'justificacion' => 'Sin evidencia de evaluación'])->assertOk()->assertJsonPath('data.estado', 'Rechazada');
    accionApi($this, $dos['id'], 'rechazo', ['version' => 1, 'justificacion' => 'No procede'])->assertOk();
    $this->getJson(URL_MOVIMIENTOS)->assertOk()->assertJsonPath('meta.total', 2);
});

it('una afirmación manual de «apto» no suple la evaluación (US4.4)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);

    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Apto', 'evaluacion_apto' => true])
        ->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Apto', 'resultado' => 'Valida'])
        ->assertStatus(422)->assertJsonPath('code', 'EVALUACION_NO_VERIFICABLE');
    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Presentada');
});

it('una evidencia que no acredita colaborador, destino, requisitos o vigencia es no verificable', function (string $modo) {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    evaluadorDeDestino($modo);
    $antes = asignacionesActuales();

    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado'])
        ->assertStatus(422)->assertJsonPath('code', 'EVALUACION_NO_VERIFICABLE');

    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Presentada')->and(asignacionesActuales())->toEqual($antes);
})->with(['otro_puesto', 'otro_colaborador', 'sin_requisitos', 'vencida', 'cae']);

it('una evaluación válida es evidencia para decidir, no aprobación automática ni un porcentaje (US4.3)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    evaluadorDeDestino('valida');

    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('Presentada')
        ->and($detalle->json('data.puede_aprobar'))->toBeTrue()
        ->and($detalle->json('data.movimiento.evaluacion.estado'))->toBe('Valida')
        ->and(array_keys($detalle->json('data.movimiento.evaluacion.contexto')))->toBe(['colaborador_id', 'puesto_id', 'requisitos', 'evaluada_en', 'resultado'])
        ->and(json_encode($detalle->json('data.movimiento.evaluacion')))->not->toContain('porcentaje');

    $aprobada = accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Cumple los requisitos'])->assertOk();

    expect($aprobada->json('data.estado'))->toBe('Programada')
        ->and(DB::table('movimientos_personal')->value('evaluacion_estado'))->toBe('Valida')
        ->and(json_decode((string) DB::table('movimientos_personal')->value('evaluacion_contexto'), true)['requisitos'])->toHaveCount(2)
        ->and($aprobada->json('data.historial.1.detalle.evaluacion.estado'))->toBe('Valida');
});

it('al incorporarse el contrato una propuesta ya presentada se aprueba sin rehacerla (US4.4b)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado'])->assertStatus(422);
    $registrada = DB::table('actuaciones_personal')->first();

    evaluadorDeDestino('valida');
    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado'])->assertOk();

    expect(DB::table('actuaciones_personal')->count())->toBe(1)
        ->and(DB::table('actuaciones_personal')->value('presentada_en'))->toBe($registrada->presentada_en)
        ->and(DB::table('actuaciones_personal')->value('motivo'))->toBe($registrada->motivo);
});

it('un destino que dejó de admitir asignaciones es una condición estructural incumplida distinta de la evaluación (US4.5)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    evaluadorDeDestino('valida');
    PosicionModel::query()->whereKey($movimiento['destino']->id)->update(['estado' => 'Suprimida']);

    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    $comprobaciones = collect($detalle->json('data.comprobaciones'))->keyBy('clave');
    expect($comprobaciones['destino']['estado'])->toBe('NoCumple')
        ->and($comprobaciones['evaluacion']['estado'])->toBe('Cumple')
        ->and($detalle->json('data.motivo_no_aprobable'))->toBe('POSICION_NO_DISPONIBLE');

    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado'])
        ->assertStatus(422)->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');
});

it('si Estructura falla no se da la verificación por cumplida y se distingue de un incumplimiento', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    evaluadorDeDestino('valida');
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

    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    expect(collect($detalle->json('data.comprobaciones'))->firstWhere('clave', 'destino')['estado'])->toBe('NoVerificable')
        ->and($detalle->json('data.puede_aprobar'))->toBeFalse();

    accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado'])
        ->assertStatus(503)->assertJsonPath('code', 'ESTRUCTURA_NO_VERIFICABLE');
    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Presentada');
});
