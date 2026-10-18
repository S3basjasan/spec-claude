<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\Exceptions\OrganigramaInconsistente;
use App\Modules\Estructura\Domain\ValueObjects\NodoDePosicion;

/**
 * Comprueba que las posiciones dadas (las vigentes) formen un único árbol: exactamente una raíz (sin supervisor), todas
 * descienden de ella y no hay ciclos. Puro: sin Laravel. No corrige nada; con una lista vacía no falla.
 */
final class ComprobadorDeArbol
{
    /**
     * @param  array<int, NodoDePosicion>  $nodos
     *
     * @throws OrganigramaInconsistente
     */
    public function comprobar(array $nodos): void
    {
        if ($nodos === []) {
            return;
        }

        $porId = [];
        foreach ($nodos as $nodo) {
            $porId[$nodo->id] = $nodo;
        }

        $raices = [];
        foreach ($porId as $nodo) {
            // Un supervisor que no está entre las vigentes deja a la posición sin camino hasta la raíz.
            if ($nodo->supervisorId === null) {
                $raices[] = $nodo->id;
            }
        }

        if ($raices === []) {
            throw new OrganigramaInconsistente(OrganigramaInconsistente::SIN_RAIZ, array_keys($porId));
        }
        if (count($raices) > 1) {
            throw new OrganigramaInconsistente(OrganigramaInconsistente::VARIAS_RAICES, $raices);
        }

        $hijas = [];
        foreach ($porId as $nodo) {
            if ($nodo->supervisorId !== null) {
                $hijas[$nodo->supervisorId][] = $nodo->id;
            }
        }

        // Recorrido desde la raíz sin repetir nodos: si alguno se visita dos veces hay un ciclo; los no visitados son
        // inalcanzables (supervisor inexistente o ciclo aislado).
        $visitados = [];
        $pendientes = [$raices[0]];
        while ($pendientes !== []) {
            $actual = array_shift($pendientes);
            if (isset($visitados[$actual])) {
                throw new OrganigramaInconsistente(OrganigramaInconsistente::CICLO, [$actual]);
            }
            $visitados[$actual] = true;
            foreach ($hijas[$actual] ?? [] as $hija) {
                $pendientes[] = $hija;
            }
        }

        $fuera = array_values(array_diff(array_keys($porId), array_keys($visitados)));
        if ($fuera !== []) {
            throw new OrganigramaInconsistente(OrganigramaInconsistente::INALCANZABLES, $fuera);
        }
    }
}
