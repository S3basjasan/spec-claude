<?php

/**
 * Reglas de arquitectura de la adecuación (Constitución I): el dominio y la aplicación no dependen de Laravel ni de otros
 * módulos, y ningún archivo de la feature importa clases internas de Estructura, Gestión de Personal ni Compensaciones.
 */
function archivosDeAdecuacion(string $carpeta): array
{
    $base = dirname(__DIR__, 4).'/app/Modules/Desarrollo/'.$carpeta;
    $nombres = ['Adecuacion', 'ParametrosDe', 'Preparacion', 'DimensionDeAdecuacion', 'EstadoDeResultado', 'CodigoDeAdecuacion', 'PesosPorDimension', 'ClaveDePermisoAdecuacion', 'CalculadorDeResultado', 'CalculadorDePreparacion', 'CalculadorDeCumplimiento', 'CalculadorDePuntaje', 'ReglasDeParametrosDeAdecuacion', 'DiagnosticoDTO', 'ArmadorDeDiagnostico', 'MiAdecuacion', 'CompararCandidatos', 'MapaDeTalento', 'OpcionesDeAdecuacion', 'GenerarAdecuacion', 'PrevisualizarAdecuacion', 'ListarAdecuaciones', 'ObtenerAdecuacion', 'ValoresDeLaPersona', 'ExperienciaDelColaborador', 'ExperienciaDesdePersonal', 'PuestoActual', 'RutasParaAdecuacion', 'OfertaFormativa', 'AutorizadorDeAdecuacion', 'PermisosDeAdecuacion', 'ResultadoDeAdecuacion', 'ComparacionInvalida', 'ObjetivoInvalido', 'ObjetivoCambio', 'TiempoDeCierreInvalido', 'PesosInvalidos', 'UmbralInvalido'];
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    $archivos = [];
    foreach ($iterador as $archivo) {
        if (! str_ends_with($archivo->getFilename(), '.php')) {
            continue;
        }
        foreach ($nombres as $n) {
            if (str_contains($archivo->getFilename(), $n)) {
                $archivos[] = $archivo->getPathname();
                break;
            }
        }
    }

    return $archivos;
}

it('el dominio de la adecuación no depende de Laravel, Eloquent ni otros módulos', function () {
    $archivos = archivosDeAdecuacion('Domain');
    expect($archivos)->not->toBeEmpty();

    foreach ($archivos as $ruta) {
        $codigo = file_get_contents($ruta);
        expect($codigo)->not->toMatch('/^use Illuminate\\\\/m')
            ->not->toMatch('/^use App\\\\Modules\\\\(Estructura|GestionPersonal|Compensaciones|Shared)\\\\/m')
            ->not->toMatch('/Infrastructure\\\\/')
            ->not->toMatch('/^use App\\\\Modules\\\\Desarrollo\\\\(Application|Presentation)\\\\/m');
    }
});

it('la aplicación de la adecuación no depende de Laravel, Eloquent ni de otros módulos', function () {
    foreach (archivosDeAdecuacion('Application') as $ruta) {
        $codigo = file_get_contents($ruta);
        expect($codigo)->not->toMatch('/^use Illuminate\\\\/m')
            ->not->toMatch('/^use App\\\\Modules\\\\(Estructura|GestionPersonal|Compensaciones)\\\\/m')
            ->not->toMatch('/^use App\\\\Modules\\\\Desarrollo\\\\(Infrastructure|Presentation)\\\\/m');
    }
});

it('solo los adaptadores usan las Facades de otros módulos y ninguna clase importa sus clases internas', function () {
    $permitidos = ['PersonalFacade', 'EstructuraFacade'];
    foreach (archivosDeAdecuacion('Infrastructure') as $ruta) {
        $codigo = file_get_contents($ruta);
        preg_match_all('/^use App\\\\Modules\\\\(Estructura|GestionPersonal|Compensaciones)\\\\([^;]+);/m', $codigo, $m, PREG_SET_ORDER);
        foreach ($m as $uso) {
            $clase = substr(strrchr($uso[2], '\\') ?: '\\'.$uso[2], 1);
            expect($permitidos)->toContain($clase);
            expect($uso[2])->toContain('Presentation\\Facades\\');
        }
    }
});
