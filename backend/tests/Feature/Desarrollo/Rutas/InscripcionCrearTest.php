<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;

beforeEach(function () {
    rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->ruta = rutaEnBd([1, 2, 3], 'Vigente');
    $this->inscribir = fn (array $cambios = []) => $this->postJson('/api/inscripciones-ruta', $cambios + [
        'ruta_id' => $this->ruta['id'],
        'colaborador_id' => 10,
        'nivel_actual_id' => $this->ruta['niveles'][0],
        'nivel_objetivo_id' => $this->ruta['niveles'][2],
    ]);
});

it('inscribe a un colaborador activo con nivel actual y objetivo y lo registra en el historial', function () {
    ($this->inscribir)()->assertCreated()
        ->assertJsonPath('data.estado', 'Activa')
        ->assertJsonPath('data.colaborador.id', 10)
        ->assertJsonPath('data.colaborador.nombre', 'Colaborador 10')
        ->assertJsonPath('data.nivel_actual.orden', 1)
        ->assertJsonPath('data.nivel_objetivo.orden', 3)
        ->assertJsonPath('data.ruta.codigo', $this->ruta['codigo']);

    $m = RutaInscripcionModel::query()->firstOrFail();
    $evento = RutaHistorialModel::query()->where('accion', 'inscrita')->firstOrFail();
    expect($m->created_by)->toBe($this->admin->id)->and($m->tenant_id)->toBe(1)->and($m->estado)->toBe('Activa')->and($m->resuelta_at)->toBeNull()
        ->and($evento->inscripcion_id)->toBe($m->id)->and($evento->tipo)->toBe('Inscripcion')->and($evento->user_id)->toBe($this->admin->id)
        ->and($evento->valor_nuevo['nivel_objetivo'])->toBe('3. Nivel 3');
});

it('permite inscribir sin nivel actual y sin nivel objetivo', function () {
    ($this->inscribir)(['nivel_actual_id' => null, 'nivel_objetivo_id' => null])->assertCreated()
        ->assertJsonPath('data.nivel_actual', null)->assertJsonPath('data.nivel_objetivo', null);
});

it('solo las rutas Vigentes admiten nuevas inscripciones', function (string $estado) {
    $ruta = rutaEnBd([1, 2], $estado);

    ($this->inscribir)(['ruta_id' => $ruta['id'], 'nivel_actual_id' => null, 'nivel_objetivo_id' => null])
        ->assertUnprocessable()->assertJsonPath('code', 'ruta_no_vigente');
    expect(RutaInscripcionModel::query()->count())->toBe(0);
})->with(['Borrador', 'Archivada']);

it('rechaza al colaborador sin vínculo vigente y responde 404 si el colaborador no existe', function () {
    ($this->inscribir)(['colaborador_id' => 99])->assertUnprocessable()->assertJsonPath('code', 'colaborador_no_activo');

    app()->instance(NombresDeReferenciaInterface::class, new class implements NombresDeReferenciaInterface
    {
        public function colaboradores(int $tenantId, array $ids): array
        {
            return [];
        }

        public function puestos(array $ids): array
        {
            return [];
        }

        public function competencias(array $ids): array
        {
            return [];
        }

        public function unidades(array $ids): array
        {
            return [];
        }
    });
    ($this->inscribir)(['colaborador_id' => 98])->assertNotFound()->assertJsonPath('code', 'colaborador_no_encontrado');
    expect(RutaInscripcionModel::query()->count())->toBe(0);
});

it('un colaborador puede estar en varias rutas pero con una sola inscripción Activa por ruta', function () {
    $otra = rutaEnBd([1, 2], 'Vigente');

    ($this->inscribir)()->assertCreated();
    ($this->inscribir)()->assertUnprocessable()->assertJsonPath('code', 'ya_inscrito')->assertJsonValidationErrors('colaborador_id');
    ($this->inscribir)(['ruta_id' => $otra['id'], 'nivel_actual_id' => null, 'nivel_objetivo_id' => $otra['niveles'][1]])->assertCreated();

    expect(RutaInscripcionModel::query()->where('colaborador_id', 10)->count())->toBe(2);
});

it('tras retirarse, el colaborador puede volver a inscribirse en la misma ruta', function () {
    inscripcionEnBd($this->ruta['id'], 10, estado: 'Retirada', motivo: 'Cambio de área');

    ($this->inscribir)()->assertCreated();
});

it('rechaza niveles de otra ruta y un objetivo que no es posterior al actual', function () {
    $otra = rutaEnBd([1, 2], 'Vigente');

    ($this->inscribir)(['nivel_actual_id' => $otra['niveles'][0]])->assertUnprocessable()->assertJsonPath('code', 'nivel_ajeno')->assertJsonValidationErrors('nivel_actual_id');
    ($this->inscribir)(['nivel_objetivo_id' => $otra['niveles'][1]])->assertUnprocessable()->assertJsonValidationErrors('nivel_objetivo_id');
    ($this->inscribir)(['nivel_actual_id' => $this->ruta['niveles'][1], 'nivel_objetivo_id' => $this->ruta['niveles'][1]])
        ->assertUnprocessable()->assertJsonPath('code', 'objetivo_no_posterior');
    ($this->inscribir)(['nivel_actual_id' => $this->ruta['niveles'][2], 'nivel_objetivo_id' => $this->ruta['niveles'][0]])
        ->assertUnprocessable()->assertJsonPath('code', 'objetivo_no_posterior');
    expect(RutaInscripcionModel::query()->count())->toBe(0);
});

it('responde 404 si la ruta no existe o es de otro tenant', function () {
    ($this->inscribir)(['ruta_id' => 9999])->assertNotFound()->assertJsonPath('code', 'ruta_no_encontrada');

    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    ($this->inscribir)(['ruta_id' => $ajena['id']])->assertNotFound();
});

it('valida los campos obligatorios', function () {
    $this->postJson('/api/inscripciones-ruta', [])->assertUnprocessable()->assertJsonValidationErrors(['ruta_id', 'colaborador_id']);
});

it('si Gestión de Personal no responde, no inscribe y avisa con 503', function () {
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

    ($this->inscribir)()->assertStatus(503)->assertJsonPath('code', 'fuente_no_disponible');
    expect(RutaInscripcionModel::query()->count())->toBe(0);
});

it('el colaborador no puede inscribir', function () {
    actuarComoUsuarioDeTenant();

    ($this->inscribir)()->assertForbidden();
    expect(RutaInscripcionModel::query()->count())->toBe(0);
});
