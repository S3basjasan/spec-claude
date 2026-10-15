<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\Entities\TipoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\BorradorDeTipo;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\UnidadAfectadaPorCadena;

/**
 * Reglas puras de la cadena de tipos de unidad (sin Laravel): nombre, borrador, token de edición simultánea y unidades
 * que quedarían en conflicto tras un reordenamiento.
 */
final class CadenaDeTipos
{
    public const MAX_NOMBRE = 50;

    public function __construct(private readonly NormalizadorDeTexto $normalizador) {}

    /**
     * Huella de la cadena: cambia si se agrega, renombra o reordena cualquier tipo.
     *
     * @param  array<int, TipoUnidad>  $tipos
     */
    public function token(array $tipos): string
    {
        usort($tipos, fn (TipoUnidad $a, TipoUnidad $b) => $a->nivel <=> $b->nivel);

        return sha1(implode('|', array_map(fn (TipoUnidad $t) => "{$t->id}:{$t->nombre}:{$t->nivel}", $tipos)));
    }

    /**
     * Mensaje de error del nombre de un tipo, o null si es válido. `$otros` son los nombres de los demás tipos.
     *
     * @param  array<int, string>  $otros
     */
    public function errorDeNombre(string $nombre, array $otros): ?string
    {
        $limpio = trim($nombre);
        if ($limpio === '') {
            return 'El nombre es obligatorio.';
        }
        if (mb_strlen($limpio) > self::MAX_NOMBRE) {
            return 'El nombre no puede superar los '.self::MAX_NOMBRE.' caracteres.';
        }

        $buscado = $this->normalizador->paraBuscar($limpio);
        foreach ($otros as $otro) {
            if ($this->normalizador->paraBuscar($otro) === $buscado) {
                return 'Ya existe un tipo con ese nombre.';
            }
        }

        return null;
    }

    /**
     * Errores de forma del borrador: ids exactos, tipo raíz primero y nombres válidos y únicos (por índice).
     *
     * @param  array<int, TipoUnidad>  $actuales
     * @param  array<int, BorradorDeTipo>  $borrador
     * @return array<string, string>
     */
    public function erroresDelBorrador(array $actuales, array $borrador): array
    {
        $errores = [];

        $idsActuales = array_map(fn (TipoUnidad $t) => $t->id, $actuales);
        $idsBorrador = array_map(fn (BorradorDeTipo $b) => $b->id, $borrador);
        sort($idsActuales);
        $ordenados = $idsBorrador;
        sort($ordenados);
        if ($idsActuales !== $ordenados) {
            return ['tipos' => 'La lista debe contener exactamente los tipos existentes, sin repetir ni faltar.'];
        }

        $raiz = null;
        foreach ($actuales as $tipo) {
            if ($tipo->esRaiz()) {
                $raiz = $tipo;
            }
        }
        if ($raiz !== null && $borrador[0]->id !== $raiz->id) {
            $errores['tipos'] = 'El tipo raíz debe seguir en el primer lugar.';
        }

        foreach ($borrador as $indice => $item) {
            $otros = [];
            foreach ($borrador as $j => $otro) {
                if ($j !== $indice) {
                    $otros[] = $otro->nombre;
                }
            }
            $error = $this->errorDeNombre($item->nombre, $otros);
            if ($error !== null) {
                $errores["tipos.$indice.nombre"] = $error;
            }
        }

        return $errores;
    }

    /**
     * Nivel resultante de cada tipo según su posición en el borrador (1..N).
     *
     * @param  array<int, BorradorDeTipo>  $borrador
     * @return array<int, int> id del tipo → nivel
     */
    public function nivelesResultantes(array $borrador): array
    {
        $niveles = [];
        foreach (array_values($borrador) as $indice => $item) {
            $niveles[$item->id] = $indice + 1;
        }

        return $niveles;
    }

    /**
     * Unidades que quedarían bajo una unidad padre cuyo tipo tendría un nivel igual o más profundo (número mayor o
     * igual) que el de su propio tipo en la cadena resultante. Los saltos de nivel (padre de nivel menor, no
     * inmediato) no son conflicto.
     *
     * @param  array<int, NodoDeUnidad>  $nodos  Todas las unidades, indexadas por id.
     * @param  array<int, BorradorDeTipo>  $borrador
     * @return array<int, UnidadAfectadaPorCadena>
     */
    public function unidadesEnConflicto(array $nodos, array $borrador): array
    {
        $niveles = $this->nivelesResultantes($borrador);
        $nombres = [];
        foreach ($borrador as $item) {
            $nombres[$item->id] = trim($item->nombre);
        }

        $afectadas = [];
        foreach ($nodos as $nodo) {
            $padre = $nodo->unidadPadreId === null ? null : ($nodos[$nodo->unidadPadreId] ?? null);
            if ($padre === null) {
                continue;
            }
            $nivelUnidad = $niveles[$nodo->tipo->id] ?? $nodo->tipo->nivel;
            $nivelPadre = $niveles[$padre->tipo->id] ?? $padre->tipo->nivel;
            if ($nivelPadre >= $nivelUnidad) {
                $afectadas[] = new UnidadAfectadaPorCadena(
                    $nodo->id,
                    $nodo->codigo,
                    $nodo->nombre,
                    $nombres[$nodo->tipo->id] ?? $nodo->tipo->nombre,
                    $nivelUnidad,
                    $padre->id,
                    $padre->codigo,
                    $padre->nombre,
                    $nombres[$padre->tipo->id] ?? $padre->tipo->nombre,
                    $nivelPadre,
                );
            }
        }

        return $afectadas;
    }
}
