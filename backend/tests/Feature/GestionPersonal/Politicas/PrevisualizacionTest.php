<?php

use Illuminate\Support\Facades\DB;

function previsualizarApi(mixed $prueba, string $tipo, int $version, array $cuerpo = [])
{
    return $prueba->postJson(urlPoliticas($tipo, "/borrador/{$version}/previsualizacion"), $cuerpo);
}

it('devuelve condiciones, diferencias, inicio y efecto sin escribir nada (PO-CA02)', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos');
    $borrador = borradorDe('Permisos', ['parametros' => ['anticipacion_dias' => 4] + parametrosValidos('Permisos')]);
    $escrituras = [];
    DB::listen(function ($consulta) use (&$escrituras) {
        if (preg_match('/^\s*(insert|update|delete)/i', $consulta->sql) === 1) {
            $escrituras[] = $consulta->sql;
        }
    });

    $respuesta = previsualizarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'inmediato']])->assertOk();

    expect($escrituras)->toBe([])
        ->and($respuesta->json('data.condiciones.parametros.anticipacion_dias'))->toBe(4)
        ->and($respuesta->json('data.diferencias'))->toHaveCount(1)
        ->and(collect($respuesta->json('data.diferencias'))->firstWhere('clave', 'anticipacion_dias'))->toMatchArray(['anterior' => 2, 'nuevo' => 4])
        ->and($respuesta->json('data.inicio_vigencia'))->not->toBeNull()
        ->and($respuesta->json('data.efecto.solicitudes_anteriores'))->toBe('conservan sus condiciones')
        ->and($respuesta->json('data.efecto.aplica_a'))->toBe('nuevas solicitudes')
        ->and($respuesta->json('data.efecto.sustituye'))->toBe(1);
});

it('lista los errores sin publicar: incompleto, inicio pasado y versión programada existente', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $borrador = borradorDe('Permisos', ['parametros' => ['disponible' => true], 'descripcion' => '']);
    $ayer = now('America/Guayaquil')->subDay()->format('Y-m-d\TH:i');

    $respuesta = previsualizarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => $ayer]])->assertOk();

    expect($respuesta->json('data.errores'))->toHaveKeys(['descripcion', 'parametros.anticipacion_dias', 'inicio.fecha_hora_local', 'publicacion'])
        ->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('numero'))->toBeNull();
});

it('sin versión vigente todas las condiciones son diferencias y no hay a quién sustituir', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Ascensos');

    $respuesta = previsualizarApi($this, 'Ascensos', $borrador->id)->assertOk();

    expect(array_column($respuesta->json('data.diferencias'), 'clave'))->toBe(['descripcion', 'disponible', 'anticipacion_dias', 'documento_adicional_obligatorio'])
        ->and($respuesta->json('data.efecto.sustituye'))->toBeNull()->and($respuesta->json('data.inicio_vigencia'))->toBeNull();
});

it('solo previsualiza borradores activos', function () {
    actuarComoAdminDeTenant();
    $publicada = politicaPublicada('Permisos');

    previsualizarApi($this, 'Permisos', $publicada->id)->assertConflict();
    previsualizarApi($this, 'Permisos', 999999)->assertNotFound();
});
