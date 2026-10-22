<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

it('crea las cuatro tablas con sus columnas', function () {
    expect(Schema::getColumnListing('rutas'))->toContain('tenant_id', 'numero', 'codigo', 'nombre', 'nombre_clave', 'tipo', 'descripcion', 'departamento_id', 'estado', 'version', 'created_by', 'updated_by')
        ->and(Schema::getColumnListing('ruta_niveles'))->toContain('tenant_id', 'ruta_id', 'orden', 'puesto_id', 'nombre', 'experiencia_minima_anios')
        ->and(Schema::getColumnListing('ruta_inscripciones'))->toContain('tenant_id', 'ruta_id', 'colaborador_id', 'nivel_actual_id', 'nivel_objetivo_id', 'estado', 'motivo', 'resuelta_by', 'resuelta_at', 'created_by')
        ->and(Schema::getColumnListing('ruta_historial'))->toContain('tenant_id', 'ruta_id', 'inscripcion_id', 'tipo', 'accion', 'valor_anterior', 'valor_nuevo', 'motivo', 'user_id', 'created_at')
        ->and(Schema::getColumnListing('ruta_historial'))->not->toContain('updated_at');
});

it('el código y el nombre son únicos dentro de cada tenant, no entre tenants', function () {
    rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta contable']);

    expect(fn () => rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta contable', 'numero' => 50, 'codigo' => 'CAR-0050']))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Otra', 'numero' => 1]))->toThrow(UniqueConstraintViolationException::class);

    // Otro tenant puede repetir nombre, número y código.
    $otra = rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta contable', 'numero' => 1, 'codigo' => 'CAR-0001'], tenantId: 2);
    expect(RutaModel::query()->where('tenant_id', 2)->count())->toBe(1)->and($otra['codigo'])->toBe('CAR-0001');
});

it('no admite el mismo orden ni el mismo puesto dos veces en una ruta', function () {
    $ruta = rutaEnBd([1, 2]);
    $atributos = ['tenant_id' => 1, 'ruta_id' => $ruta['id'], 'nombre' => 'x', 'experiencia_minima_anios' => null];

    expect(fn () => RutaNivelModel::query()->create($atributos + ['orden' => 1, 'puesto_id' => 5]))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => RutaNivelModel::query()->create($atributos + ['orden' => 9, 'puesto_id' => 1]))->toThrow(UniqueConstraintViolationException::class);
});

it('el índice parcial admite una sola inscripción Activa por colaborador y ruta, pero no limita las demás', function () {
    $ruta = rutaEnBd([1, 2]);
    inscripcionEnBd($ruta['id'], 10);

    expect(fn () => inscripcionEnBd($ruta['id'], 10))->toThrow(UniqueConstraintViolationException::class);

    // Una Retirada y una Finalizada del mismo colaborador no chocan con la Activa.
    inscripcionEnBd($ruta['id'], 10, estado: 'Retirada', motivo: 'Cambio de área');
    inscripcionEnBd($ruta['id'], 10, estado: 'Finalizada');
    inscripcionEnBd($ruta['id'], 11);

    expect(RutaInscripcionModel::query()->count())->toBe(4);
});

it('el historial se inserta con fecha y sin updated_at', function () {
    $ruta = rutaEnBd([1, 2]);
    $evento = RutaHistorialModel::query()->create([
        'tenant_id' => 1, 'ruta_id' => $ruta['id'], 'tipo' => 'Ruta', 'accion' => 'creada', 'valor_nuevo' => ['nombre' => 'Ruta 1'], 'user_id' => 1,
    ]);

    expect($evento->fresh()->created_at)->not->toBeNull()->and($evento->fresh()->valor_nuevo)->toBe(['nombre' => 'Ruta 1']);
});
