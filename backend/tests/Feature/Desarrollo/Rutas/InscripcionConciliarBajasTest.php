<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\UseCases\ConciliarBajasDeInscripciones;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use Tests\Support\Desarrollo\ColaboradoresVigentesFalso;

beforeEach(function () {
    $this->falsos = rutasFalsos(['vigentes' => [10 => 'Ana Paredes', 11 => 'Luis Mora']]);
    $this->admin = actuarComoAdminDeTenant();
    $this->ruta = rutaEnBd([1, 2, 3], 'Vigente');
    [$this->n1, $this->n2, $this->n3] = $this->ruta['niveles'];
    $this->vigente = inscripcionEnBd($this->ruta['id'], 10, $this->n1, $this->n2);
    $this->baja = inscripcionEnBd($this->ruta['id'], 12, $this->n1, $this->n3); // sin vínculo vigente
});

it('retira con el motivo «Baja del colaborador» a quien ya no tiene vínculo vigente, con el sistema como autor', function () {
    $retiradas = app(ConciliarBajasDeInscripciones::class)->ejecutar(1);

    $baja = RutaInscripcionModel::query()->find($this->baja->id);
    $evento = RutaHistorialModel::query()->where('accion', 'retirada')->firstOrFail();
    expect($retiradas)->toBe(1)
        ->and($baja->estado)->toBe('Retirada')->and($baja->motivo)->toBe('Baja del colaborador')->and($baja->resuelta_at)->not->toBeNull()->and($baja->resuelta_by)->toBeNull()
        ->and($baja->nivel_actual_id)->toBe($this->n1)
        ->and(RutaInscripcionModel::query()->find($this->vigente->id)->estado)->toBe('Activa')
        ->and($evento->user_id)->toBeNull()->and($evento->motivo)->toBe('Baja del colaborador')->and($evento->inscripcion_id)->toBe($this->baja->id);
});

it('es idempotente', function () {
    $conciliar = app(ConciliarBajasDeInscripciones::class);

    expect($conciliar->ejecutar(1))->toBe(1)->and($conciliar->ejecutar(1))->toBe(0);
    expect(RutaHistorialModel::query()->where('accion', 'retirada')->count())->toBe(1);
});

it('no toca las inscripciones Finalizadas ni las ya Retiradas', function () {
    $final = inscripcionEnBd($this->ruta['id'], 13, $this->n2, $this->n3, 'Finalizada');
    $retirada = inscripcionEnBd($this->ruta['id'], 14, estado: 'Retirada', motivo: 'Cambio');

    app(ConciliarBajasDeInscripciones::class)->ejecutar(1);

    expect(RutaInscripcionModel::query()->find($final->id)->estado)->toBe('Finalizada')
        ->and(RutaInscripcionModel::query()->find($retirada->id)->motivo)->toBe('Cambio');
});

it('si Gestión de Personal no responde, no retira a nadie', function () {
    app()->instance(ColaboradoresVigentesInterface::class, new class implements ColaboradoresVigentesInterface
    {
        public function buscar(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array
        {
            throw new RuntimeException('Personal caído');
        }

        public function vigentesPorIds(int $tenantId, array $ids, string $fecha): array
        {
            throw new RuntimeException('Personal caído');
        }
    });

    expect(app(ConciliarBajasDeInscripciones::class)->ejecutar(1))->toBe(0)
        ->and(RutaInscripcionModel::query()->where('estado', 'Activa')->count())->toBe(2);
});

it('solo concilia el tenant indicado', function () {
    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    $deOtro = inscripcionEnBd($ajena['id'], 12, tenantId: 2);

    app(ConciliarBajasDeInscripciones::class)->ejecutar(1);

    expect(RutaInscripcionModel::query()->find($deOtro->id)->estado)->toBe('Activa');
});

it('listar inscripciones concilia antes las bajas', function () {
    $r = $this->getJson("/api/inscripciones-ruta?ruta_id={$this->ruta['id']}&estado=Activa")->assertOk();

    expect(array_column($r->json('data'), 'id'))->toBe([$this->vigente->id])
        ->and(RutaInscripcionModel::query()->find($this->baja->id)->estado)->toBe('Retirada');
});

it('ver la ruta concilia antes las bajas y la cantera las excluye', function () {
    $d = $this->getJson("/api/rutas/{$this->ruta['id']}")->assertOk()->json('data');

    expect($d['inscritos_count'])->toBe(1)->and(array_column($d['niveles'], 'inscritos_activos'))->toBe([1, 0, 0]);
});

it('el comando rutas:conciliar-bajas produce el mismo efecto', function () {
    $this->artisan('rutas:conciliar-bajas')->expectsOutput('Inscripciones retiradas por baja: 1')->assertSuccessful();

    expect(RutaInscripcionModel::query()->find($this->baja->id)->estado)->toBe('Retirada');
});

it('el doble de vigentes usado en las pruebas respeta los ids no listados', function () {
    expect((new ColaboradoresVigentesFalso([10 => 'Ana']))->vigentesPorIds(1, [10, 12], '2026-06-15'))->toBe([10]);
});
