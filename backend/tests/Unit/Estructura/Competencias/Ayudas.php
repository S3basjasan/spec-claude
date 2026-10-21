<?php

use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Application\Services\ValidadorDeEscala;
use Tests\Unit\Estructura\Competencias\EscalasEnMemoria;
use Tests\Unit\Estructura\Competencias\UsuariosFalsos;

/**
 * Datos completos de una competencia: escala Letras (id 1), 4 grados, 16 comportamientos y 4 preguntas.
 *
 * @param  array<int, string>|null  $categorias
 */
function datosUnitarios(string $nombre = 'Liderazgo', int $escalaId = 1, ?array $categorias = null): DatosCompetenciaDTO
{
    return new DatosCompetenciaDTO(
        $nombre,
        ' Guiar a otros. ',
        'Especifica',
        $escalaId,
        $categorias,
        ['a', 'b', 'c', 'd'],
        [['a1', 'a2', 'a3', 'a4'], ['b1', 'b2', 'b3', 'b4'], ['c1', 'c2', 'c3', 'c4'], ['d1', 'd2', 'd3', 'd4']],
        ['p1', 'p2', 'p3', 'p4'],
    );
}

function validadorUnitario(): ValidadorDeEscala
{
    return new ValidadorDeEscala(new EscalasEnMemoria);
}

function auditoriaUnitaria(): AuditoriaDeCompetencia
{
    return new AuditoriaDeCompetencia(new UsuariosFalsos);
}
