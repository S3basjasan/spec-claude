<?php

use App\Modules\GestionPersonal\Domain\Entities\AccionDePolitica;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentPoliticaRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

it('dos publicaciones del mismo borrador: solo una prospera y no hay números duplicados ni dos vigentes', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');

    $primera = publicarApi($this, 'Permisos', $borrador->id)->assertCreated();
    $segunda = publicarApi($this, 'Permisos', $borrador->id)->assertConflict();

    expect($primera->json('data.numero'))->toBe(1)->and($segunda->json('code'))->toBe('BORRADOR_MODIFICADO')
        ->and(DB::table('politica_versiones')->whereNotNull('numero')->pluck('numero')->all())->toBe([1])
        ->and(DB::table('politicas_personal')->where('tipo', 'Permisos')->value('ultimo_numero'))->toBe(1)
        ->and(DB::table('politica_acciones')->where('accion', 'Publicacion')->count())->toBe(1);
});

it('una lectura previa obsoleta de los borradores no permite crear dos: el índice único lo impide (409)', function () {
    actuarComoAdminDeTenant();
    $existente = borradorDe('Permisos');
    app()->bind(PoliticaRepositoryInterface::class, fn () => new class extends EloquentPoliticaRepository
    {
        // Simula un lector cuya instantánea es anterior al borrador que otra operación acaba de confirmar.
        public function versionesDe(int $tenantId, array $politicaIds): array
        {
            return array_fill_keys($politicaIds, []);
        }
    });

    $respuesta = $this->postJson(urlPoliticas('permisos', '/borrador'))->assertConflict();

    expect($respuesta->json('code'))->toBe('BORRADOR_EXISTENTE')->and($respuesta->json('errors.borrador_id'))->toBe($existente->id)
        ->and(DB::table('politica_versiones')->whereNull('publicada_en')->count())->toBe(1);
});

it('si falla algo después de reservar el número, todo se revierte y el número no se pierde ni se duplica', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    app()->bind(PoliticaRepositoryInterface::class, fn () => new class extends EloquentPoliticaRepository
    {
        public function registrarAccion(int $tenantId, int $politicaId, int $versionId, string $accion, int $actorId, ?string $motivo, ?string $operacionId, ?string $operacionHash, DateTimeImmutable $ahora): AccionDePolitica
        {
            throw new RuntimeException('fallo simulado de auditoría');
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => publicarApi($this, 'Permisos', $borrador->id))->toThrow(RuntimeException::class);

    expect(DB::table('politica_versiones')->where('id', $borrador->id)->value('numero'))->toBeNull()
        ->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('publicada_en'))->toBeNull()
        ->and(DB::table('politicas_personal')->where('tipo', 'Permisos')->value('ultimo_numero'))->toBe(0);

    app()->bind(PoliticaRepositoryInterface::class, EloquentPoliticaRepository::class);
    $this->withExceptionHandling();
    expect(publicarApi($this, 'Permisos', $borrador->id)->assertCreated()->json('data.numero'))->toBe(1);
});

it('publicación y retiro en el instante de inicio dan un único resultado coherente', function () {
    actuarComoAdminDeTenant();
    $inicio = new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('UTC'));
    $anterior = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'))]);
    $programada = politicaPublicada('Permisos', ['inicio' => $inicio, 'publicada' => new DateTimeImmutable('2026-10-01 00:00:00', new DateTimeZone('UTC'))]);

    Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
    $retiro = $this->postJson(urlPoliticas('permisos', "/versiones/{$programada->id}/retiro"), ['justificacion' => 'Tarde'], ['Idempotency-Key' => (string) Str::uuid()]);

    $detalle = $this->getJson(urlPoliticas('permisos'))->json('data');
    expect($retiro->status())->toBe(409)->and($detalle['vigente']['id'])->toBe($programada->id)
        ->and(collect($detalle['versiones'])->where('estado', 'Vigente'))->toHaveCount(1)
        ->and(collect($detalle['versiones'])->firstWhere('id', $anterior->id)['estado'])->toBe('Sustituida');
});

it('una escritura intermedia no deja dos versiones vigentes ni dos programadas', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Licencias', ['inicio' => new DateTimeImmutable('-30 days', new DateTimeZone('UTC'))]);
    $borrador = borradorDe('Licencias');
    publicarApi($this, 'Licencias', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => now('America/Guayaquil')->addDays(3)->format('Y-m-d\TH:i')]])->assertCreated();
    $otro = $this->postJson(urlPoliticas('licencias', '/borrador'))->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('licencias', "/borrador/{$otro}"), ['version' => 1, 'descripcion' => 'D', 'parametros' => parametrosValidos('Licencias')])->assertOk();

    publicarApi($this, 'Licencias', $otro, [], null, 2)->assertConflict();

    $estados = collect($this->getJson(urlPoliticas('licencias'))->json('data.versiones'))->pluck('estado')->all();
    expect(array_count_values($estados))->toBe(['Programada' => 1, 'Vigente' => 1]);
});
