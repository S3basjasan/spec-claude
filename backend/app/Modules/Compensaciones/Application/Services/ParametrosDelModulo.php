<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Domain\Entities\CambioDeParametro;
use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Lectura de los parámetros del módulo. Es el punto único que usan las demás specs de Compensaciones
 * (sin Facade: son del mismo módulo).
 *
 * Valor vigente en una fecha: la fila de la clave con `vigente_desde` más reciente que no la supere (con empate, la
 * última creada). Sin fila, el valor por defecto; el de los puntos por colaborador es el mayor entre 100 y la
 * puntuación de la categoría activa más alta.
 */
final class ParametrosDelModulo
{
    private readonly DateTimeZone $zona;

    public function __construct(
        private readonly ParametroRepository $parametros,
        private readonly CategoriaRepository $categorias,
        string $zonaHoraria,
    ) {
        $this->zona = new DateTimeZone($zonaHoraria);
    }

    public function ahora(): DateTimeImmutable
    {
        return CarbonImmutable::now($this->zona);
    }

    /** Fecha de hoy (Y-m-d) en la zona del módulo. */
    public function hoy(): string
    {
        return $this->ahora()->format('Y-m-d');
    }

    /** Día 1 del mes siguiente (Y-m-d) en la zona del módulo. */
    public function primeroDelMesSiguiente(): string
    {
        return $this->ahora()->modify('first day of next month')->format('Y-m-d');
    }

    public function enLaZona(DateTimeInterface $momento): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($momento)->setTimezone($this->zona);
    }

    public function valorVigente(ClaveDeParametro $clave, ?string $fecha = null): int
    {
        $fila = $this->parametros->vigenteEn($clave, $fecha ?? $this->hoy());

        return $fila?->valor ?? $this->porDefecto($clave);
    }

    /** Cambio ya guardado que todavía no rige (solo los puntos por colaborador), o null. */
    public function programado(ClaveDeParametro $clave): ?CambioDeParametro
    {
        return $this->parametros->programadaDespuesDe($clave, $this->hoy());
    }

    public function porDefecto(ClaveDeParametro $clave): int
    {
        if ($clave !== ClaveDeParametro::PuntosPorColaborador) {
            return $clave->porDefecto();
        }

        $mayor = $this->categoriaActivaMasAlta();

        return max($clave->porDefecto(), $mayor?->puntuacion->valor ?? 0);
    }

    /** Categoría activa de mayor puntuación (con empate, la de menor id), o null si no hay activas. */
    public function categoriaActivaMasAlta(): ?Categoria
    {
        $mayor = null;
        foreach ($this->categorias->activas() as $categoria) {
            if (
                $mayor === null
                || $categoria->puntuacion->valor > $mayor->puntuacion->valor
                || ($categoria->puntuacion->valor === $mayor->puntuacion->valor && ($categoria->id ?? PHP_INT_MAX) < ($mayor->id ?? PHP_INT_MAX))
            ) {
                $mayor = $categoria;
            }
        }

        return $mayor;
    }
}
