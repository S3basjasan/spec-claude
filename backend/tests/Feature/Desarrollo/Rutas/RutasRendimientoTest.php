<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;
use Illuminate\Support\Facades\DB;

/**
 * SC-007: lista, escalera e inscripciones responden en menos de 2 s con 100 rutas y 1 000 inscripciones. Es lento de
 * preparar, así que solo corre con RUTAS_RENDIMIENTO=1:  RUTAS_RENDIMIENTO=1 vendor/bin/pest --filter=RutasRendimientoTest
 */
it('cumple SC-007 con 100 rutas y 1 000 inscripciones', function () {
    $falsos = rutasFalsos(['vigentes' => array_combine(range(1, 1000), array_map(fn ($i) => "Colaborador {$i}", range(1, 1000)))]);
    actuarComoAdminDeTenant();

    DB::transaction(function () {
        for ($r = 1; $r <= 100; $r++) {
            RutaModel::query()->create([
                'tenant_id' => 1, 'numero' => $r, 'codigo' => sprintf('CAR-%04d', $r), 'nombre' => "Ruta {$r}", 'nombre_clave' => "ruta {$r}", 'tipo' => 'Especialista',
                'departamento_id' => 1, 'estado' => 'Vigente', 'version' => 1, 'created_by' => 1, 'updated_by' => 1,
            ]);
            for ($n = 1; $n <= 5; $n++) {
                RutaNivelModel::query()->create(['tenant_id' => 1, 'ruta_id' => $r, 'orden' => $n, 'puesto_id' => $n, 'nombre' => "Nivel {$n}", 'experiencia_minima_anios' => $n]);
            }
        }
        $niveles = RutaNivelModel::query()->orderBy('id')->pluck('id', 'id')->all();
        $primero = array_key_first($niveles);
        for ($i = 1; $i <= 1000; $i++) {
            $ruta = (($i - 1) % 100) + 1;
            $base = $primero + ($ruta - 1) * 5;
            RutaInscripcionModel::query()->create(['tenant_id' => 1, 'ruta_id' => $ruta, 'colaborador_id' => $i, 'nivel_actual_id' => $base, 'nivel_objetivo_id' => $base + 3, 'estado' => 'Activa', 'created_by' => 1]);
        }
    });

    $medir = function (string $url) {
        $inicio = microtime(true);
        $this->getJson($url)->assertOk();

        return microtime(true) - $inicio;
    };

    $lista = $medir('/api/rutas?per_page=100');
    $detalle = $medir('/api/rutas/1');
    $inscripciones = $medir('/api/inscripciones-ruta?ruta_id=1&per_page=20');

    fwrite(STDERR, sprintf("\nRutas SC-007: lista %.2fs · detalle %.2fs · inscripciones %.2fs\n", $lista, $detalle, $inscripciones));

    expect($lista)->toBeLessThan(2.0)->and($detalle)->toBeLessThan(2.0)->and($inscripciones)->toBeLessThan(2.0);
    // El detalle lee el perfil de cada puesto distinto una sola vez, no una vez por nivel.
    expect(array_sum($falsos['puestos']->consultas))->toBeLessThanOrEqual(5);
})->skip(fn () => getenv('RUTAS_RENDIMIENTO') !== '1', 'Prueba de rendimiento: ejecutar con RUTAS_RENDIMIENTO=1');
