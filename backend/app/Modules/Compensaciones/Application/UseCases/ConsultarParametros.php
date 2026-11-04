<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\ParametroDTO;
use App\Modules\Compensaciones\Application\Ports\NombresDeUsuarioPort;
use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;

final class ConsultarParametros
{
    public function __construct(
        private readonly ParametroRepository $parametros,
        private readonly ParametrosDelModulo $servicio,
        private readonly NombresDeUsuarioPort $usuarios,
    ) {}

    /** Máximo permitido para la puntuación base de una categoría (menor entre el valor vigente y el programado). */
    public function puntuacionMaximaDeCategoria(): int
    {
        return $this->servicio->puntuacionMaximaDeCategoria();
    }

    /** @return array<int, ParametroDTO> las seis claves, en el orden del enum */
    public function ejecutar(): array
    {
        $ultimas = [];
        foreach (ClaveDeParametro::cases() as $clave) {
            $ultimas[$clave->value] = $this->parametros->ultima($clave);
        }

        $nombres = $this->usuarios->nombresDe(array_values(array_map(
            fn ($fila) => $fila->creadoPor,
            array_filter($ultimas),
        )));

        $resultado = [];
        foreach (ClaveDeParametro::cases() as $clave) {
            $ultima = $ultimas[$clave->value];
            $programado = $this->servicio->programado($clave);

            $ultimoCambio = null;
            if ($ultima !== null) {
                $ultimoCambio = [
                    'por' => $nombres[$ultima->creadoPor] ?? null,
                    'en' => $this->servicio->enLaZona($ultima->creadoEn)->format('c'),
                    'valor_anterior' => $this->parametros->anteriorA($ultima)?->valor,
                    'valor_nuevo' => $ultima->valor,
                ];
            }

            $resultado[] = new ParametroDTO(
                $clave->value,
                $this->servicio->valorVigente($clave),
                $programado === null ? null : ['valor' => $programado->valor, 'vigente_desde' => $programado->vigenteDesde],
                $clave->minimo(),
                $clave->maximo(),
                $this->servicio->porDefecto($clave),
                $ultima?->id ?? 0,
                $ultimoCambio,
            );
        }

        return $resultado;
    }
}
