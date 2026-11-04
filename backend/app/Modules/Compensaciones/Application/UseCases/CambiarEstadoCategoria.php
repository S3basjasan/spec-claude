<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Services\ArmadorDeCategoriaDTO;
use App\Modules\Compensaciones\Application\Services\AuditoriaDeCategoria;
use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaModificadaPorOtro;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;

final class CambiarEstadoCategoria
{
    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly AuditoriaDeCategoria $auditoria,
        private readonly ArmadorDeCategoriaDTO $armador,
        private readonly ParametrosDelModulo $parametros,
    ) {}

    /**
     * Activar o desactivar. Pedir el estado actual con la versión vigente no cambia nada ni audita; con una
     * versión desactualizada se rechaza como modificación concurrente. Se puede activar con competencia inactiva.
     *
     * @throws CategoriaNoEncontrada
     * @throws CategoriaModificadaPorOtro
     * @throws DatosDeCategoriaInvalidos al activar una categoría cuya puntuación supera el máximo permitido
     */
    public function ejecutar(int $id, EstadoCategoria $estado, string $versionLeida, int $actorId): CategoriaDTO
    {
        $resultado = $this->categorias->enTransaccion(function () use ($id, $estado, $versionLeida, $actorId) {
            $actual = $this->categorias->porId($id) ?? throw new CategoriaNoEncontrada;

            if (! $actual->coincideVersion($versionLeida)) {
                throw new CategoriaModificadaPorOtro;
            }

            if ($actual->estado === $estado) {
                return $actual;
            }

            // La regla de los parámetros solo mira categorías activas: sin esto, reactivar una categoría por encima del
            // máximo permitido rompería el tope. Desactivar nunca puede violarlo.
            if ($estado === EstadoCategoria::Activo) {
                $maximo = $this->parametros->puntuacionMaximaDeCategoria();

                if ($actual->puntuacion->valor > $maximo) {
                    throw new DatosDeCategoriaInvalidos('puntuacion_base', "Baja primero la puntuación a {$maximo} o menos.");
                }
            }

            $guardada = $this->categorias->cambiarEstadoSiVigente($actual, $estado, $actorId)
                ?? throw new CategoriaModificadaPorOtro;

            $this->auditoria->alModificar($actual, $guardada, $actorId);

            return $guardada;
        });

        return $this->armador->uno($resultado, true);
    }
}
