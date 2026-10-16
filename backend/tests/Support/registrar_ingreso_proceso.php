<?php

/**
 * Proceso auxiliar de la prueba de concurrencia (GP02-CA05): arranca la aplicación contra la base indicada por el
 * entorno, espera la señal de inicio y confirma un ingreso. Imprime una línea JSON con el resultado.
 *
 * Uso: php registrar_ingreso_proceso.php <fichaId> <posicionId> <fecha> <claveUuid> <archivoDeInicio>
 */

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\UseCases\RegistrarIngreso;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $fichaId, $posicionId, $fecha, $clave, $inicio] = $argv;

// Barrera: todos los procesos arrancan la aplicación y esperan juntos la señal.
$limite = microtime(true) + 30;
while (! file_exists($inicio) && microtime(true) < $limite) {
    usleep(200);
}

try {
    $resultado = $app->make(RegistrarIngreso::class)->ejecutar(
        new ContextoDeTenant(1, 'Prueba', 'America/Guayaquil'),
        1,
        (int) $fichaId,
        $clave,
        ['fecha_ingreso' => $fecha, 'tipo_contrato' => 'indefinido', 'posicion_id' => (int) $posicionId],
    );
    echo json_encode(['ok' => true, 'creado' => $resultado->creado]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => (new ReflectionClass($e))->getShortName()]);
}
