<?php

use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use Illuminate\Support\Facades\DB;

function sincronizarMicro(int $microId, array $competenciaIds, ?int $actor = null): void
{
    app(CatalogoFormativoRepositoryInterface::class)->sincronizarCompetenciasDeMicrocontenido($microId, $competenciaIds, $actor);
}

it('crea y quita vínculos, registra el historial del Catálogo y sube la versión', function () {
    $micro = MicrocontenidoModel::factory()->publicado()->create(['titulo' => 'Guía']);
    configurarFormativa(10, null, [], [$micro->id], 3);

    sincronizarMicro($micro->id, [20, 30], 7);

    expect(DB::table('competencia_microcontenido')->where('microcontenido_id', $micro->id)->pluck('competencia_id')->sort()->values()->all())->toBe([20, 30]);

    $historial = DB::table('competencia_formativa_historial')->orderBy('id')->get();
    expect($historial->pluck('tipo_cambio')->unique()->all())->toBe(['Microcontenido'])
        ->and($historial->where('accion', 'Asociado')->pluck('competencia_id')->sort()->values()->all())->toBe([20, 30])
        ->and($historial->where('accion', 'Quitado')->pluck('competencia_id')->all())->toBe([10])
        ->and($historial->first()->user_id)->toBe(7)
        ->and($historial->firstWhere('accion', 'Asociado')->valor_nuevo)->toBe('Guía');

    expect((int) DB::table('competencia_formativa')->where('competencia_id', 10)->value('version'))->toBe(4)
        ->and((int) DB::table('competencia_formativa')->where('competencia_id', 20)->value('version'))->toBe(1);
});

it('no registra nada si los vínculos no cambian', function () {
    $micro = MicrocontenidoModel::factory()->publicado()->create();
    configurarFormativa(10, null, [], [$micro->id], 2);

    sincronizarMicro($micro->id, [10]);

    expect(DB::table('competencia_formativa_historial')->count())->toBe(0)
        ->and((int) DB::table('competencia_formativa')->where('competencia_id', 10)->value('version'))->toBe(2);
});

it('solo un micro-contenido Publicado cuenta como disponible en el Catálogo', function () {
    $publicado = MicrocontenidoModel::factory()->publicado()->create();
    $borrador = MicrocontenidoModel::factory()->create();
    $archivado = MicrocontenidoModel::factory()->archivado()->create();
    configurarFormativa(10, null, [], [$publicado->id, $borrador->id, $archivado->id]);
    actuarComoAdmin();

    $ids = array_column($this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=microcontenido')->json('data'), 'id');

    expect($ids)->toBe([$publicado->id])
        ->and(app(CatalogoFormativoRepositoryInterface::class)->idsDisponibles(TipoRecurso::Microcontenido))->toBe([$publicado->id]);
});
