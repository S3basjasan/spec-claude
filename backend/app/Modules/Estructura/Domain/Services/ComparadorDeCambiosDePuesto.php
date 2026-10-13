<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;

/**
 * Decide qué cambios de un puesto quedan en el historial (research.md D-07).
 *
 * - Datos del puesto: una fila por campo cambiado.
 * - Listas: una fila por elemento dado de alta, de baja o modificado.
 * - El orden de las funciones (y la numeración que se deriva de él) no se audita.
 * - Con `$anterior` nulo (creación) todo es un alta con valor anterior nulo.
 *
 * Es una regla pura de dominio: no depende de Laravel ni de Eloquent.
 */
final class ComparadorDeCambiosDePuesto
{
    /**
     * @return array<int, CambioAuditado>
     */
    public function comparar(?InstantaneaDePuesto $anterior, InstantaneaDePuesto $nuevo): array
    {
        $cambios = [];

        $this->campo($cambios, 'codigo', $anterior?->codigo, $nuevo->codigo);
        $this->campo($cambios, 'nombre', $anterior?->nombre, $nuevo->nombre);
        $this->campo($cambios, 'descripcion', $anterior?->descripcion, $nuevo->descripcion);
        $this->campo($cambios, 'mision', $anterior?->mision, $nuevo->mision);
        $this->campo($cambios, 'estado', $anterior?->estado->value, $nuevo->estado->value);

        $this->lista(
            $cambios,
            'funciones',
            $this->indexar($anterior?->funciones ?? [], fn (FuncionPuesto $f) => $f->clave()),
            $this->indexar($nuevo->funciones, fn (FuncionPuesto $f) => $f->clave()),
            fn (FuncionPuesto $f) => $f->tipo->etiqueta().': '.trim($f->descripcion),
        );

        $this->lista(
            $cambios,
            'requisitos',
            $this->indexar($anterior?->requisitos ?? [], fn (RequisitoPuesto $r) => $r->clave()),
            $this->indexar($nuevo->requisitos, fn (RequisitoPuesto $r) => $r->clave()),
            fn (RequisitoPuesto $r) => $r->tipo->etiqueta().': '.trim($r->descripcion),
        );

        $this->lista(
            $cambios,
            'competencias',
            $this->indexar($anterior?->competencias ?? [], fn (CompetenciaDePuesto $c) => (string) $c->competencia->id),
            $this->indexar($nuevo->competencias, fn (CompetenciaDePuesto $c) => (string) $c->competencia->id),
            fn (CompetenciaDePuesto $c) => $c->competencia->codigo.' '.$c->competencia->nombre.': grado '.$c->gradoRequerido,
        );

        return $cambios;
    }

    /**
     * @param  array<int, CambioAuditado>  $cambios
     */
    private function campo(array &$cambios, string $campo, ?string $anterior, ?string $nuevo): void
    {
        if ($anterior !== $nuevo) {
            $cambios[] = new CambioAuditado($campo, $anterior, $nuevo);
        }
    }

    /**
     * Elementos por clave. Si una clave se repite (no debería, la validación lo impide), conserva el primero.
     *
     * @template T
     *
     * @param  array<int, T>  $elementos
     * @param  callable(T): string  $clave
     * @return array<string, T>
     */
    private function indexar(array $elementos, callable $clave): array
    {
        $indexados = [];
        foreach ($elementos as $elemento) {
            $indexados[$clave($elemento)] ??= $elemento;
        }

        return $indexados;
    }

    /**
     * Modificaciones y bajas, en el orden anterior; después las altas, en el orden nuevo.
     *
     * @template T
     *
     * @param  array<int, CambioAuditado>  $cambios
     * @param  array<string, T>  $anteriores
     * @param  array<string, T>  $nuevos
     * @param  callable(T): string  $valor
     */
    private function lista(array &$cambios, string $campo, array $anteriores, array $nuevos, callable $valor): void
    {
        foreach ($anteriores as $clave => $elemento) {
            if (! array_key_exists($clave, $nuevos)) {
                $cambios[] = new CambioAuditado($campo, $valor($elemento), null);

                continue;
            }

            $antes = $valor($elemento);
            $despues = $valor($nuevos[$clave]);
            if ($antes !== $despues) {
                $cambios[] = new CambioAuditado($campo, $antes, $despues);
            }
        }

        foreach ($nuevos as $clave => $elemento) {
            if (! array_key_exists($clave, $anteriores)) {
                $cambios[] = new CambioAuditado($campo, null, $valor($elemento));
            }
        }
    }
}
