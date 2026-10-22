<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\MicroNoPublicable;
use App\Modules\Desarrollo\Domain\Exceptions\TipoInmutable;
use App\Modules\Desarrollo\Domain\Exceptions\TransicionMicroInvalida;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoMicro;

/**
 * Reglas puras del ciclo de vida de un micro-contenido (sin Laravel ni Eloquent): transiciones de estado,
 * requisitos para publicar y tipo inmutable.
 */
final class ReglasDeMicrocontenido
{
    /**
     * @throws TransicionMicroInvalida
     */
    public function validarTransicion(EstadoMicro $actual, EstadoMicro $nuevo): void
    {
        if ($actual === $nuevo || ! $actual->puedeIrA($nuevo)) {
            throw new TransicionMicroInvalida($actual->value, $nuevo->value);
        }
    }

    /**
     * Datos que faltan para publicar, en un orden estable: titulo, duracion, url, competencias, preguntas.
     *
     * @return array<int, string>
     */
    public function faltantes(string $titulo, int $duracionMinutos, TipoMicro $tipo, ?string $url, int $preguntas, int $competenciasActivas): array
    {
        $faltantes = [];

        if (trim($titulo) === '') {
            $faltantes[] = 'titulo';
        }
        if ($duracionMinutos < 1) {
            $faltantes[] = 'duracion';
        }
        if ($tipo->requiereUrl() && ($url === null || trim($url) === '')) {
            $faltantes[] = 'url';
        }
        if ($competenciasActivas < 1) {
            $faltantes[] = 'competencias';
        }
        if ($tipo->esQuiz() && $preguntas < 1) {
            $faltantes[] = 'preguntas';
        }

        return $faltantes;
    }

    /**
     * @throws MicroNoPublicable
     */
    public function validarPublicable(string $titulo, int $duracionMinutos, TipoMicro $tipo, ?string $url, int $preguntas, int $competenciasActivas): void
    {
        $faltantes = $this->faltantes($titulo, $duracionMinutos, $tipo, $url, $preguntas, $competenciasActivas);
        if ($faltantes !== []) {
            throw new MicroNoPublicable($faltantes);
        }
    }

    /**
     * @throws TipoInmutable
     */
    public function validarTipoInmutable(TipoMicro $guardado, TipoMicro $pedido): void
    {
        if ($guardado !== $pedido) {
            throw new TipoInmutable;
        }
    }
}
