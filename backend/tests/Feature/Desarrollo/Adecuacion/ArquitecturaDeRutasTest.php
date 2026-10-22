<?php

/** Reglas de las rutas de la adecuación (Constitución I y FR-018): inmutables y autorizadas por clave de permiso. */
it('no existe ninguna ruta PUT, PATCH ni DELETE sobre una adecuación concreta', function () {
    foreach (app('router')->getRoutes() as $ruta) {
        if (! str_starts_with($ruta->uri(), 'api/adecuaciones')) {
            continue;
        }
        $metodos = array_diff($ruta->methods(), ['GET', 'HEAD', 'POST']);
        if ($metodos === []) {
            continue;
        }
        expect($metodos)->toBe(['PUT'])->and($ruta->uri())->toBe('api/adecuaciones/parametros');
    }
});

it('los controladores no consultan el rol: la autorización vive en el middleware por clave de permiso', function () {
    foreach (['AdecuacionesController', 'MiAdecuacionController'] as $controlador) {
        $codigo = file_get_contents(base_path("app/Modules/Desarrollo/Presentation/Controllers/{$controlador}.php"));
        expect($codigo)->not->toContain("tokenCan('admin')")->not->toContain('->role');
    }
    foreach (app('router')->getRoutes() as $ruta) {
        if (str_starts_with($ruta->uri(), 'api/adecuaciones') && $ruta->uri() !== 'api/adecuaciones/permisos') {
            expect(implode(',', $ruta->gatherMiddleware()))->toContain('permiso:desarrollo.adecuacion.');
        }
    }
});
