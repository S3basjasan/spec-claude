<?php

use App\Modules\GestionPersonal\Presentation\Facades\PoliticasFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('procesos_salida_prueba', function ($tabla) {
        $tabla->id();
        $tabla->foreignId('politica_version_id')->constrained('politica_versiones')->restrictOnDelete();
    });
    Schema::create('tareas_proceso_prueba', function ($tabla) {
        $tabla->id();
        $tabla->foreignId('proceso_id')->constrained('procesos_salida_prueba')->cascadeOnDelete();
        $tabla->unsignedInteger('orden');
        $tabla->string('nombre');
        $tabla->string('descripcion')->nullable();
        $tabla->boolean('exige_evidencia');
        $tabla->string('responsable')->nullable();
    });
});

/** Iniciar un offboarding: copia las tareas de la versión aplicable a tareas propias del proceso. */
function iniciarOffboardingDePrueba(): int
{
    return DB::transaction(function () {
        $p = PoliticasFacade::seleccionarParaPresentar(1, 'Offboarding', new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $proceso = DB::table('procesos_salida_prueba')->insertGetId(['politica_version_id' => $p['version_id']]);
        foreach ($p['parametros']['tareas'] as $tarea) {
            DB::table('tareas_proceso_prueba')->insert([
                'proceso_id' => $proceso, 'orden' => $tarea['orden'], 'nombre' => $tarea['nombre'],
                'descripcion' => $tarea['descripcion'], 'exige_evidencia' => $tarea['exige_evidencia'],
            ]);
        }

        return $proceso;
    });
}

it('la fachada entrega las tareas selladas en orden y el proceso las copia (PO-CA36)', function () {
    actuarComoAdminDeTenant();
    $id = $this->postJson(urlPoliticas('offboarding', '/borrador'))->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('offboarding', "/borrador/{$id}"), ['version' => 1, 'descripcion' => 'Cierre administrativo', 'parametros' => ['tareas' => [
        ['nombre' => 'Devolver equipos', 'descripcion' => 'Laptop y credenciales', 'exige_evidencia' => true],
        ['nombre' => 'Cerrar accesos', 'descripcion' => null, 'exige_evidencia' => false],
        ['nombre' => 'Acta de entrega', 'exige_evidencia' => true],
    ]]])->assertOk();
    publicarApi($this, 'Offboarding', $id, [], null, 2)->assertCreated();

    $proceso = iniciarOffboardingDePrueba();

    $tareas = DB::table('tareas_proceso_prueba')->where('proceso_id', $proceso)->orderBy('orden')->get();
    expect($tareas->pluck('nombre')->all())->toBe(['Devolver equipos', 'Cerrar accesos', 'Acta de entrega'])
        ->and($tareas->pluck('exige_evidencia')->map(fn ($e) => (bool) $e)->all())->toBe([true, false, true])
        ->and($tareas[0]->descripcion)->toBe('Laptop y credenciales')->and($tareas[1]->descripcion)->toBeNull()
        ->and($tareas->pluck('orden')->all())->toBe([1, 2, 3]);
});

it('publicar otra versión de la plantilla no agrega ni quita tareas de un proceso iniciado (PO-CA37)', function () {
    actuarComoAdminDeTenant();
    $v1 = publicarApi($this, 'Offboarding', borradorDe('Offboarding')->id)->assertCreated()->json('data.id');
    $proceso = iniciarOffboardingDePrueba();

    $nuevo = $this->postJson(urlPoliticas('offboarding', '/borrador'), ['base_version_id' => $v1])->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('offboarding', "/borrador/{$nuevo}"), ['version' => 1, 'parametros' => ['tareas' => [
        ['nombre' => 'Otra tarea distinta', 'exige_evidencia' => false],
    ]]])->assertOk();
    publicarApi($this, 'Offboarding', $nuevo, [], null, 2)->assertCreated();

    $segundo = iniciarOffboardingDePrueba();
    expect(DB::table('tareas_proceso_prueba')->where('proceso_id', $proceso)->pluck('nombre')->all())->toBe(['Devolver equipos', 'Cerrar accesos'])
        ->and(DB::table('tareas_proceso_prueba')->where('proceso_id', $segundo)->pluck('nombre')->all())->toBe(['Otra tarea distinta']);
});

it('un nombre de persona o función en la plantilla no produce permisos ni responsables (PO-CA38)', function () {
    actuarComoAdminDeTenant();
    $id = $this->postJson(urlPoliticas('offboarding', '/borrador'))->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('offboarding', "/borrador/{$id}"), ['version' => 1, 'descripcion' => 'D', 'parametros' => ['tareas' => [
        ['nombre' => 'Que Ana Núñez (Gerente de TI) apruebe la entrega', 'exige_evidencia' => true],
    ]]])->assertOk();
    publicarApi($this, 'Offboarding', $id, [], null, 2)->assertCreated();

    $proceso = iniciarOffboardingDePrueba();

    $tarea = DB::table('tareas_proceso_prueba')->where('proceso_id', $proceso)->first();
    expect($tarea->responsable)->toBeNull()
        ->and(DB::table('users')->where('name', 'like', '%Ana Núñez%')->count())->toBe(0);
});

it('la definición de Offboarding expone las reglas fijas y rechaza omitirlas (PO-CA16)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Offboarding');

    $definicion = $this->getJson(urlPoliticas('offboarding'))->assertOk()->json('data.definicion');
    expect(array_column($definicion['reglas_fijas'], 'clave'))->toContain('OFF-MOTIVO-FECHA', 'OFF-SIN-RETROACTIVAS', 'OFF-SIN-RETENCION', 'OFF-RESPONSABLES');

    foreach (['motivo_obligatorio' => false, 'fecha_efectiva_obligatoria' => false, 'salidas_retroactivas' => true, 'prolonga_vinculo' => true, 'tareas_retienen_salida' => true] as $clave => $valor) {
        $respuesta = $this->patchJson(urlPoliticas('offboarding', "/borrador/{$borrador->id}"), ['version' => 1, 'parametros' => ['tareas' => [], $clave => $valor]])->assertUnprocessable();
        expect($respuesta->json('errors')["parametros.{$clave}"][0])->toContain('Regla fija');
    }
});

it('Offboarding no tiene disponibilidad: nunca produce NoAdmiteSolicitudes', function () {
    actuarComoUsuarioDeTenant();
    politicaPublicada('Offboarding', ['parametros' => ['tareas' => []]]);

    $respuesta = $this->getJson(urlPoliticas('offboarding', '/aplicable'))->assertOk();

    expect($respuesta->json('data.situacion'))->toBe('Vigente')->and($respuesta->json('data.parametros.tareas'))->toBe([]);
    expect(PoliticasFacade::aplicablePara(1, 'Offboarding', new DateTimeImmutable('now', new DateTimeZone('UTC')))['situacion'])->toBe('Vigente');
});
