<?php

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Concurrencia real (GP02-CA05, SC-004): archivo SQLite temporal y procesos PHP independientes. No usa :memory:.
 */
function raizBackend(): string
{
    return dirname(__DIR__, 4);
}

function entornoDeConcurrencia(string $archivo): array
{
    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $archivo,
        'DB_BUSY_TIMEOUT' => '10000',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];
}

function pdoDe(string $archivo): PDO
{
    $pdo = new PDO('sqlite:'.$archivo);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 10000');

    return $pdo;
}

function nuevaFicha(PDO $pdo, string $numero): int
{
    $pdo->prepare(
        'INSERT INTO colaboradores (tenant_id, codigo, tipo_identificacion, numero_identificacion, identificacion_clave, nombres, apellidos, nombres_busqueda, apellidos_busqueda, version, operacion_id, operacion_hash, created_by, updated_by, created_at, updated_at)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1, 1, datetime(?), datetime(?))'
    )->execute(['EMP-C'.$numero, 'Cedula', $numero, $numero, 'Prueba', 'Concurrente', 'prueba', 'concurrente',
        (string) Str::uuid(), str_repeat('b', 64), 'now', 'now']);

    return (int) $pdo->lastInsertId();
}

function nuevaPosicion(PDO $pdo): int
{
    $base = $pdo->query('SELECT puesto_id, unidad_organizacional_id FROM posiciones WHERE unidad_organizacional_id IS NOT NULL AND tenant_id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("INSERT INTO posiciones (puesto_id, unidad_organizacional_id, estado, tenant_id, created_at, updated_at) VALUES (?, ?, 'Vacante', 1, datetime('now'), datetime('now'))")
        ->execute([$base['puesto_id'], $base['unidad_organizacional_id']]);

    return (int) $pdo->lastInsertId();
}

/**
 * Lanza los procesos, espera a que arranquen y da la señal de inicio a todos a la vez.
 *
 * @param  list<array{ficha: int, posicion: int}>  $intentos
 * @return list<array<string, mixed>>
 */
function confirmarALaVez(string $archivo, array $intentos, string $fecha): array
{
    $inicio = $archivo.'.inicio.'.Str::random(6);
    $procesos = [];

    foreach ($intentos as $intento) {
        $proceso = new Process(
            [PHP_BINARY, raizBackend().'/tests/Support/registrar_ingreso_proceso.php', (string) $intento['ficha'], (string) $intento['posicion'], $fecha, (string) Str::uuid(), $inicio],
            raizBackend(),
            entornoDeConcurrencia($archivo),
            null,
            60,
        );
        $proceso->start();
        $procesos[] = $proceso;
    }

    usleep(1_500_000); // deja que todos arranquen la aplicación y queden esperando la barrera
    file_put_contents($inicio, '1');

    $resultados = [];
    foreach ($procesos as $proceso) {
        $proceso->wait();
        $resultados[] = json_decode(trim($proceso->getOutput()), true) ?? ['ok' => false, 'error' => 'SinSalida: '.$proceso->getErrorOutput()];
    }
    @unlink($inicio);

    return $resultados;
}

beforeAll(function () {
    $archivo = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gp02-concurrencia-'.Str::random(8).'.sqlite';
    touch($archivo);
    $GLOBALS['gp02_archivo'] = $archivo;

    $migrar = new Process([PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction'], raizBackend(), entornoDeConcurrencia($archivo), null, 240);
    $migrar->run();
    $GLOBALS['gp02_migrada'] = $migrar->isSuccessful();
});

afterAll(function () {
    if (isset($GLOBALS['gp02_archivo'])) {
        @unlink($GLOBALS['gp02_archivo']);
    }
});

it('dos ingresos incompatibles a la misma posición: exactamente uno prospera (GP02-CA05)', function () {
    expect($GLOBALS['gp02_migrada'])->toBeTrue();
    $archivo = $GLOBALS['gp02_archivo'];
    $pdo = pdoDe($archivo);
    $fecha = now('America/Guayaquil')->toDateString();

    foreach (range(1, 4) as $ronda) {
        $posicion = nuevaPosicion($pdo);
        $fichaA = nuevaFicha($pdo, "9100$ronda");
        $fichaB = nuevaFicha($pdo, "9200$ronda");

        $resultados = confirmarALaVez($archivo, [
            ['ficha' => $fichaA, 'posicion' => $posicion],
            ['ficha' => $fichaB, 'posicion' => $posicion],
        ], $fecha);

        $correctos = array_values(array_filter($resultados, fn ($r) => $r['ok'] === true));
        $rechazos = array_values(array_filter($resultados, fn ($r) => $r['ok'] === false));
        $abiertas = (int) $pdo->query("SELECT COUNT(*) FROM asignaciones_laborales WHERE posicion_id = $posicion AND fecha_fin IS NULL")->fetchColumn();

        expect($correctos)->toHaveCount(1, json_encode($resultados))
            ->and($rechazos)->toHaveCount(1)
            ->and($rechazos[0]['error'])->toBe('PosicionNoDisponible')
            ->and($abiertas)->toBe(1);
    }
});

it('dos ingresos de la misma ficha a posiciones distintas: exactamente uno prospera', function () {
    expect($GLOBALS['gp02_migrada'])->toBeTrue();
    $archivo = $GLOBALS['gp02_archivo'];
    $pdo = pdoDe($archivo);
    $fecha = now('America/Guayaquil')->toDateString();

    foreach (range(1, 3) as $ronda) {
        $ficha = nuevaFicha($pdo, "9300$ronda");
        $posicionA = nuevaPosicion($pdo);
        $posicionB = nuevaPosicion($pdo);

        $resultados = confirmarALaVez($archivo, [
            ['ficha' => $ficha, 'posicion' => $posicionA],
            ['ficha' => $ficha, 'posicion' => $posicionB],
        ], $fecha);

        $correctos = array_filter($resultados, fn ($r) => $r['ok'] === true);
        $rechazos = array_values(array_filter($resultados, fn ($r) => $r['ok'] === false));
        $vinculos = (int) $pdo->query("SELECT COUNT(*) FROM vinculos_laborales WHERE colaborador_id = $ficha")->fetchColumn();

        expect($correctos)->toHaveCount(1, json_encode($resultados))
            ->and($rechazos[0]['error'])->toBe('VinculoSuperpuesto')
            ->and($vinculos)->toBe(1);
    }
});

it('el mismo ingreso confirmado dos veces a la vez crea un solo vínculo', function () {
    expect($GLOBALS['gp02_migrada'])->toBeTrue();
    $archivo = $GLOBALS['gp02_archivo'];
    $pdo = pdoDe($archivo);
    $ficha = nuevaFicha($pdo, '939001');
    $posicion = nuevaPosicion($pdo);
    $fecha = now('America/Guayaquil')->toDateString();
    $inicio = $archivo.'.mismo.inicio';
    $clave = (string) Str::uuid();

    $procesos = [];
    foreach (range(1, 2) as $i) {
        $proceso = new Process(
            [PHP_BINARY, raizBackend().'/tests/Support/registrar_ingreso_proceso.php', (string) $ficha, (string) $posicion, $fecha, $clave, $inicio],
            raizBackend(),
            entornoDeConcurrencia($archivo),
            null,
            60,
        );
        $proceso->start();
        $procesos[] = $proceso;
    }
    usleep(1_500_000);
    file_put_contents($inicio, '1');
    $salidas = [];
    foreach ($procesos as $proceso) {
        $proceso->wait();
        $salidas[] = json_decode(trim($proceso->getOutput()), true);
    }
    @unlink($inicio);

    $vinculos = (int) $pdo->query("SELECT COUNT(*) FROM vinculos_laborales WHERE colaborador_id = $ficha")->fetchColumn();

    expect($vinculos)->toBe(1)
        ->and(array_filter($salidas, fn ($s) => $s['ok'] === true))->toHaveCount(2)
        ->and(count(array_filter($salidas, fn ($s) => $s['creado'] === true)))->toBe(1);
});
