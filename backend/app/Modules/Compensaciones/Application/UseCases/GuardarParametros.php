<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CambioSolicitadoDTO;
use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Domain\Exceptions\ParametrosModificadosPorOtro;
use App\Modules\Compensaciones\Domain\Exceptions\PuntosPorDebajoDeCategoria;
use App\Modules\Compensaciones\Domain\Exceptions\ValorDeParametroInvalido;
use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;

final class GuardarParametros
{
    public function __construct(
        private readonly ParametroRepository $parametros,
        private readonly ParametrosDelModulo $servicio,
    ) {}

    /**
     * Todo o nada: si algún cambio falla (rango, piso por categoría o versión) no se guarda ninguno. Solo se inserta
     * una fila por clave cuyo valor realmente cambió; nunca se actualiza ni se borra una fila.
     *
     * @param  array<int, CambioSolicitadoDTO>  $cambios
     *
     * @throws ValorDeParametroInvalido
     * @throws PuntosPorDebajoDeCategoria
     * @throws ParametrosModificadosPorOtro
     */
    public function ejecutar(array $cambios, int $actorId): void
    {
        $cambios = array_values($cambios);
        $this->validar($cambios);

        $this->parametros->enTransaccion(function () use ($cambios, $actorId): void {
            foreach ($cambios as $cambio) {
                $actual = $this->parametros->ultima($cambio->clave)?->id ?? 0;
                if ($actual !== $cambio->version) {
                    throw new ParametrosModificadosPorOtro;
                }
            }

            $mayor = $this->servicio->categoriaActivaMasAlta();

            foreach ($cambios as $indice => $cambio) {
                if (
                    $cambio->clave === ClaveDeParametro::PuntosPorColaborador
                    && $mayor !== null
                    && $cambio->valor < $mayor->puntuacion->valor
                ) {
                    throw new PuntosPorDebajoDeCategoria($indice, $mayor->nombre->valor, $mayor->puntuacion->valor);
                }
            }

            foreach ($cambios as $cambio) {
                $ultimoGuardado = $this->parametros->ultima($cambio->clave)?->valor ?? $this->servicio->porDefecto($cambio->clave);
                if ($cambio->valor === $ultimoGuardado) {
                    continue;
                }

                $this->parametros->insertar(
                    $cambio->clave,
                    $cambio->valor,
                    $cambio->clave->esDiferida() ? $this->servicio->primeroDelMesSiguiente() : $this->servicio->hoy(),
                    $actorId,
                    $this->servicio->ahora(),
                );
            }
        });
    }

    /** @param array<int, CambioSolicitadoDTO> $cambios */
    private function validar(array $cambios): void
    {
        $vistas = [];
        foreach ($cambios as $indice => $cambio) {
            if (isset($vistas[$cambio->clave->value])) {
                throw new ValorDeParametroInvalido($indice, 'El parámetro está repetido.');
            }
            $vistas[$cambio->clave->value] = true;

            if (! $cambio->clave->admite($cambio->valor)) {
                throw new ValorDeParametroInvalido(
                    $indice,
                    "Debe ser un número entero entre {$cambio->clave->minimo()} y {$cambio->clave->maximo()}.",
                );
            }
        }
    }
}
