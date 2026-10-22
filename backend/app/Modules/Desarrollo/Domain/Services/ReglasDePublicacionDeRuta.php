<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;

/** Condiciones para que una ruta pase a Vigente: al menos 2 niveles y todos con puesto activo. */
final class ReglasDePublicacionDeRuta
{
    public const MINIMO_DE_NIVELES = 2;

    /**
     * @param  list<int>  $puestosInactivos  ids de puesto que ya no están activos
     * @return list<string> lo que falta para publicar; vacío si la ruta es publicable
     */
    public function motivos(Ruta $ruta, array $puestosInactivos): array
    {
        $motivos = [];

        if (count($ruta->niveles) < self::MINIMO_DE_NIVELES) {
            $motivos[] = 'La ruta necesita al menos 2 niveles.';
        }

        $inactivos = array_values(array_filter($ruta->niveles, fn (NivelDeRuta $n) => in_array($n->puestoId, $puestosInactivos, true)));
        if ($inactivos !== []) {
            $nombres = implode(', ', array_map(fn (NivelDeRuta $n) => "{$n->orden}. {$n->nombre}", $inactivos));
            $motivos[] = "Estos niveles tienen un puesto inactivo: {$nombres}.";
        }

        return $motivos;
    }
}
